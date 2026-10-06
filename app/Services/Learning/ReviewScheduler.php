<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\MasteryLevel;
use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\ExerciseAttempt;
use App\Models\FlashcardReview;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds and maintains the revision queue (docs/06 module G, docs/08
 * screen 7). Derived items are reconciled on every sync:
 *
 *  - due flashcards        -> item_type card
 *  - quiz weak concepts    -> item_type concept (until mastery >= practicing)
 *  - last attempt failed   -> item_type exercise
 *
 * Snoozed items become due again once their due_at passes; done items are
 * never resurrected by sync (the learner cleared them explicitly).
 */
final class ReviewScheduler
{
    public const REASON_DUE_CARD = 'due_card';

    public const REASON_WEAK_CONCEPT = 'weak_concept';

    public const REASON_FAILED_ATTEMPT = 'failed_attempt';

    public function syncDue(User $user): void
    {
        $this->reactivateSnoozed($user);
        $this->syncCards($user);
        $this->syncWeakConcepts($user);
        $this->syncFailedExercises($user);
    }

    /**
     * Items the learner should work on right now.
     *
     * @return Collection<int, ReviewItem>
     */
    public function dueItems(User $user): Collection
    {
        return ReviewItem::query()
            ->where('user_id', $user->id)
            ->where('status', ReviewItemStatus::Due->value)
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->get();
    }

    /**
     * The earliest upcoming review, used by the empty state.
     */
    public function nextDueAt(User $user): ?Carbon
    {
        $next = ReviewItem::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ReviewItemStatus::Due->value, ReviewItemStatus::Snoozed->value])
            ->where('due_at', '>', now())
            ->orderBy('due_at')
            ->value('due_at');

        return $next !== null ? Carbon::parse((string) $next) : null;
    }

    public function snooze(ReviewItem $item, int $days = 1): void
    {
        $item->snooze($days);
    }

    public function markDone(ReviewItem $item): void
    {
        $item->complete();
    }

    private function reactivateSnoozed(User $user): void
    {
        ReviewItem::query()
            ->where('user_id', $user->id)
            ->where('status', ReviewItemStatus::Snoozed->value)
            ->where('due_at', '<=', now())
            ->update(['status' => ReviewItemStatus::Due->value]);
    }

    private function syncCards(User $user): void
    {
        $dueReviews = FlashcardReview::query()
            ->where('user_id', $user->id)
            ->where('next_review_at', '<=', now())
            ->get(['card_id', 'next_review_at']);

        foreach ($dueReviews as $review) {
            ReviewItem::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'item_type' => ReviewItemType::Card,
                    'item_id' => $review->card_id,
                ],
                [
                    'reason' => self::REASON_DUE_CARD,
                    'due_at' => $review->next_review_at,
                    'status' => ReviewItemStatus::Due,
                ],
            );
        }

        // Cards graded since the last sync are no longer due.
        $gradedIds = FlashcardReview::query()
            ->where('user_id', $user->id)
            ->where('next_review_at', '>', now())
            ->pluck('card_id');

        ReviewItem::query()
            ->where('user_id', $user->id)
            ->where('item_type', ReviewItemType::Card->value)
            ->where('status', ReviewItemStatus::Due->value)
            ->whereIn('item_id', $gradedIds)
            ->update(['status' => ReviewItemStatus::Done->value]);
    }

    private function syncWeakConcepts(User $user): void
    {
        $weakSlugs = [];

        foreach (QuizAttempt::query()->where('user_id', $user->id)->get(['weak_concepts']) as $attempt) {
            foreach ((array) ($attempt->weak_concepts ?? []) as $slug) {
                $weakSlugs[(string) $slug] = true;
            }
        }

        if ($weakSlugs === []) {
            return;
        }

        $conceptRows = Concept::query()
            ->whereIn('slug', array_keys($weakSlugs))
            ->get(['id', 'slug']);

        $masteryLevels = [];
        foreach (ConceptMastery::query()->where('user_id', $user->id)->get(['concept_id', 'level']) as $row) {
            $masteryLevels[$row->concept_id] = $row->level;
        }

        foreach ($conceptRows as $concept) {
            $level = $masteryLevels[$concept->id] ?? MasteryLevel::Unseen;

            $isWeak = $level->rank() < MasteryLevel::Practicing->rank();

            ReviewItem::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'item_type' => ReviewItemType::Concept,
                    'item_id' => $concept->id,
                ],
                [
                    'concept_id' => $concept->id,
                    'reason' => self::REASON_WEAK_CONCEPT,
                    'due_at' => now(),
                    'status' => $isWeak ? ReviewItemStatus::Due : ReviewItemStatus::Done,
                ],
            );
        }
    }

    private function syncFailedExercises(User $user): void
    {
        /** @var array<int, AttemptResult> $latest */
        $latest = [];

        $attempts = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['exercise_id', 'result']);

        foreach ($attempts as $attempt) {
            $latest[$attempt->exercise_id] ??= $attempt->result;
        }

        foreach ($latest as $exerciseId => $result) {
            if ($result === AttemptResult::Correct) {
                ReviewItem::query()
                    ->where('user_id', $user->id)
                    ->where('item_type', ReviewItemType::Exercise->value)
                    ->where('item_id', $exerciseId)
                    ->update(['status' => ReviewItemStatus::Done->value]);

                continue;
            }

            ReviewItem::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'item_type' => ReviewItemType::Exercise,
                    'item_id' => $exerciseId,
                ],
                [
                    'reason' => self::REASON_FAILED_ATTEMPT,
                    'due_at' => now(),
                    'status' => ReviewItemStatus::Due,
                ],
            );
        }
    }
}
