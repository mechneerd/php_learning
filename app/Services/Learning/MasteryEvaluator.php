<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\ExerciseType;
use App\Enums\MasteryLevel;
use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Central authority for concept_mastery (docs/04 section 8, docs/11
 * section 2). UI never sets levels directly; every write flows through
 * promote()/demote() so the evidence matrix stays consistent.
 *
 * Evidence matrix (cumulative ladder):
 *   any key            -> learning   (reading alone caps here)
 *   + easy             -> practicing
 *   + medium + debug   -> comfortable
 *   all six keys       -> mastered
 */
final class MasteryEvaluator
{
    public const READ = 'read';

    public const RECALL = 'recall';

    public const EASY = 'easy';

    public const MEDIUM = 'medium';

    public const DEBUG = 'debug';

    public const MIXED = 'mixed';

    public const DEMOTION_REVIEW_REASON = 'mastery_demoted';

    public const IDLE_REVIEW_REASON = 'idle_30d';

    public const FAILURE_REVIEW_REASON = 'consecutive_failures';

    public const IDLE_DAYS = 30;

    public const FAILURE_THRESHOLD = 3;

    /**
     * Evidence keys earned by a correct attempt on this exercise.
     *
     * @return list<string>
     */
    public function evidenceKeysForExercise(Exercise $exercise, AttemptResult $result): array
    {
        if ($result !== AttemptResult::Correct) {
            return [];
        }

        $keys = [];

        if ($exercise->difficulty->value === 'easy') {
            $keys[] = self::EASY;
        }

        if ($exercise->difficulty->value === 'medium') {
            $keys[] = self::MEDIUM;
        }

        if (in_array($exercise->type, [ExerciseType::FindError, ExerciseType::Fix], true)) {
            $keys[] = self::DEBUG;
        }

        if (count($this->conceptTags($exercise)) >= 2) {
            $keys[] = self::MIXED;
        }

        return $keys;
    }

    /**
     * Concepts this exercise is tagged with: its concept_id plus any
     * extra tags carried in expected_answer.concept_tags.
     *
     * @return list<int>
     */
    public function conceptTags(Exercise $exercise): array
    {
        $tags = [];

        if ($exercise->concept_id !== null) {
            $tags[] = $exercise->concept_id;
        }

        $extra = $exercise->expected_answer['concept_tags'] ?? [];
        foreach ((array) $extra as $tag) {
            $tags[] = (int) $tag;
        }

        return array_values(array_unique($tags));
    }

    /**
     * The level this evidence set earns under the cumulative ladder.
     *
     * @param  list<string>  $keys
     */
    public function levelForEvidence(array $keys): MasteryLevel
    {
        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            return MasteryLevel::Unseen;
        }

        $has = fn (string $key): bool => in_array($key, $keys, true);

        if ($has(self::READ) && $has(self::RECALL) && $has(self::EASY)
            && $has(self::MEDIUM) && $has(self::DEBUG) && $has(self::MIXED)) {
            return MasteryLevel::Mastered;
        }

        if ($has(self::EASY) && $has(self::MEDIUM) && $has(self::DEBUG)) {
            return MasteryLevel::Comfortable;
        }

        if ($has(self::EASY)) {
            return MasteryLevel::Practicing;
        }

        // Any evidence is at least "learning"; read/recall never climb higher
        // on their own (reading alone caps at learning).
        return MasteryLevel::Learning;
    }

    /**
     * Merge new evidence keys into the learner's row and raise the level
     * if the matrix now earns more. Never downgrades.
     */
    public function promote(User $user, Concept $concept, string ...$keys): ConceptMastery
    {
        $mastery = ConceptMastery::query()->firstOrCreate(
            ['user_id' => $user->id, 'concept_id' => $concept->id],
            ['level' => MasteryLevel::Unseen, 'evidence' => []],
        );

        $evidence = $mastery->evidence ?? [];
        foreach (array_unique($keys) as $key) {
            $evidence[$key] = ($evidence[$key] ?? 0) + 1;
        }

        $mastery->evidence = $evidence;

        $earned = $this->levelForEvidence(array_keys($evidence));

        if ($earned->rank() > $mastery->level->rank()) {
            $mastery->level = $earned;
        }

        if ($mastery->level === MasteryLevel::Mastered && $mastery->mastered_at === null) {
            $mastery->mastered_at = Carbon::now();
        }

        if ($mastery->level !== MasteryLevel::Mastered) {
            $mastery->mastered_at = null;
        }

        $mastery->save();

        return $mastery;
    }

    /**
     * Process one exercise attempt: correct attempts feed the evidence
     * matrix for every tagged concept; failures are counted for the
     * 3-consecutive-failures demotion (docs/11 section 2).
     */
    public function recordAttempt(User $user, Exercise $exercise, AttemptResult $result): void
    {
        foreach ($this->conceptTags($exercise) as $conceptId) {
            $concept = Concept::query()->find($conceptId);

            if ($concept === null) {
                continue;
            }

            if ($result === AttemptResult::Correct) {
                $keys = $this->evidenceKeysForExercise($exercise, $result);

                if ($keys !== []) {
                    $this->promote($user, $concept, ...$keys);
                }

                continue;
            }

            $this->checkConsecutiveFailures($user, $concept);
        }
    }

    /**
     * Drop the learner one level (floor: unseen) and queue a review item.
     * Returns null when there is no row or the row is already unseen.
     */
    public function demote(User $user, Concept $concept, string $reason): ?ConceptMastery
    {
        return DB::transaction(function () use ($user, $concept, $reason): ?ConceptMastery {
            $mastery = ConceptMastery::query()
                ->where('user_id', $user->id)
                ->where('concept_id', $concept->id)
                ->first();

            if ($mastery === null || $mastery->level === MasteryLevel::Unseen) {
                return null;
            }

            $lowered = match ($mastery->level) {
                MasteryLevel::Mastered => MasteryLevel::Comfortable,
                MasteryLevel::Comfortable => MasteryLevel::Practicing,
                MasteryLevel::Practicing => MasteryLevel::Learning,
                default => MasteryLevel::Unseen,
            };

            $mastery->level = $lowered;
            $mastery->mastered_at = null;
            $mastery->save();

            ReviewItem::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'item_type' => ReviewItemType::Concept,
                    'item_id' => $concept->id,
                ],
                [
                    'concept_id' => $concept->id,
                    'reason' => $reason,
                    'due_at' => now(),
                    'status' => ReviewItemStatus::Due,
                ],
            );

            return $mastery;
        });
    }

    /**
     * The 30-day rule: a mastered concept idle since before the threshold
     * drops one level (to comfortable) and queues a review. Returns how
     * many rows were demoted.
     */
    public function applyIdleDemotions(User $user): int
    {
        $stale = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->where('level', MasteryLevel::Mastered->value)
            ->where('mastered_at', '<', now()->subDays(self::IDLE_DAYS))
            ->with('concept')
            ->get();

        $count = 0;

        foreach ($stale as $mastery) {
            if ($this->demote($user, $mastery->concept, self::IDLE_REVIEW_REASON) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 3 consecutive non-correct attempts on exercises tagged with this
     * concept drop the learner one level and queue a review.
     */
    private function checkConsecutiveFailures(User $user, Concept $concept): void
    {
        $recent = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->whereHas('exercise', fn ($query) => $query->where('concept_id', $concept->id))
            ->orderByDesc('created_at')
            ->limit(self::FAILURE_THRESHOLD)
            ->get(['result']);

        if ($recent->count() < self::FAILURE_THRESHOLD) {
            return;
        }

        foreach ($recent as $attempt) {
            if ($attempt->result === AttemptResult::Correct) {
                return;
            }
        }

        $this->demote($user, $concept, self::FAILURE_REVIEW_REASON);
    }
}
