<?php

namespace App\Services\Ai;

use App\Enums\MasteryLevel;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Builds the learner + lesson context payload for the tutor (docs/10
 * Pipeline B) and trims it to a character ceiling.
 */
final class ContextAssembler
{
    public function __construct(private readonly ?int $charCeiling = null) {}

    /**
     * @return array{learner: array<string, mixed>, current: array<string, mixed>|null, history: array<string, mixed>, mode: string}
     */
    public function assemble(User $user, ?Lesson $lesson, string $mode = 'tutor'): array
    {
        $payload = [
            'learner' => $this->learner($user),
            'current' => $lesson === null ? null : $this->lesson($lesson),
            'history' => $this->history($user),
            'mode' => $mode,
        ];

        return $this->trim($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function learner(User $user): array
    {
        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->with('lesson.stage:id,number')
            ->latest('last_at')
            ->first();

        return [
            'stage' => $progress?->lesson->stage->number ?? 0,
            'mastered' => $this->conceptSlugs($user, MasteryLevel::Mastered),
            'practicing' => $this->conceptSlugs($user, MasteryLevel::Practicing),
            'weak' => $this->conceptSlugs($user, MasteryLevel::Learning),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lesson(Lesson $lesson): array
    {
        $blocks = $lesson->blocks()
            ->get(['id', 'lesson_id', 'ord', 'type', 'payload'])
            ->map(static fn ($block) => $block->text())
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->values()
            ->take(10)
            ->all();

        return [
            'lesson' => $lesson->slug,
            'title' => $lesson->title,
            'chapter' => $lesson->chapter?->number,
            'pages' => [$lesson->page_printed_from, $lesson->page_printed_to],
            'citation' => $lesson->citation(),
            'concepts' => $lesson->concepts()->limit(10)->pluck('name')->values()->all(),
            'source_blocks' => $blocks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function history(User $user): array
    {
        $attempts = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->with('exercise:id,prompt')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'exercise_id', 'result'])
            ->map(static fn (ExerciseAttempt $attempt): array => [
                'result' => $attempt->result->value,
                'prompt' => mb_substr((string) $attempt->exercise?->prompt, 0, 120),
            ])
            ->values()
            ->all();

        $quizzes = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get(['id', 'lesson_id', 'score'])
            ->map(static fn (QuizAttempt $attempt): array => [
                'score' => $attempt->score,
                'lesson_id' => $attempt->lesson_id,
            ])
            ->values()
            ->all();

        return [
            'last_attempts' => $attempts,
            'quiz_scores' => $quizzes,
        ];
    }

    /**
     * @return list<string>
     */
    private function conceptSlugs(User $user, MasteryLevel $level): array
    {
        $ids = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->where('level', $level->value)
            ->limit(20)
            ->pluck('concept_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return array_values(Concept::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->limit(20)
            ->get(['slug'])
            ->map(static fn (Concept $concept): string => $concept->slug)
            ->all());
    }

    /**
     * Shrink until the JSON encoding fits the ceiling. Order: source blocks
     * (truncate, then drop), whole lesson, history, learner lists.
     *
     * @param  array{learner: array<string, mixed>, current: array<string, mixed>|null, history: array<string, mixed>, mode: string}  $payload
     * @return array{learner: array<string, mixed>, current: array<string, mixed>|null, history: array<string, mixed>, mode: string}
     */
    private function trim(array $payload): array
    {
        $ceiling = $this->charCeiling ?? (int) config('ai.max_tokens') * 4;

        while (strlen((string) json_encode($payload)) > $ceiling) {
            $next = $this->shrink($payload);

            if ($next === $payload) {
                break;
            }

            $payload = $next;
        }

        return $payload;
    }

    /**
     * @param  array{learner: array<string, mixed>, current: array<string, mixed>|null, history: array<string, mixed>, mode: string}  $payload
     * @return array{learner: array<string, mixed>, current: array<string, mixed>|null, history: array<string, mixed>, mode: string}
     */
    private function shrink(array $payload): array
    {
        $blocks = $payload['current']['source_blocks'] ?? [];

        if (is_array($blocks) && $blocks !== []) {
            $last = count($blocks) - 1;
            $text = (string) $blocks[$last];

            if (mb_strlen($text) > 120) {
                $blocks[$last] = mb_substr($text, 0, (int) (mb_strlen($text) / 2));
            } else {
                array_pop($blocks);
            }

            $payload['current']['source_blocks'] = $blocks;

            return $payload;
        }

        if ($payload['current'] !== null) {
            $payload['current'] = null;

            return $payload;
        }

        foreach (['last_attempts', 'quiz_scores'] as $key) {
            if (! empty($payload['history'][$key])) {
                array_pop($payload['history'][$key]);

                return $payload;
            }
        }

        foreach (['mastered', 'practicing', 'weak'] as $key) {
            if (! empty($payload['learner'][$key])) {
                array_pop($payload['learner'][$key]);

                return $payload;
            }
        }

        return $payload;
    }
}
