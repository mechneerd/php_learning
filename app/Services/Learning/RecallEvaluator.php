<?php

namespace App\Services\Learning;

use App\Models\Concept;
use App\Models\Lesson;
use App\Models\RecallAttempt;
use App\Models\User;
use App\Services\Ai\BudgetExceeded;
use App\Services\Ai\PromptRunner;
use App\Services\Ai\Prompts\RecallScorer;
use RuntimeException;

/**
 * EXPLAIN step of the learning loop (docs/11): rubric-score the learner's
 * own words, persist the attempt, promote `recall` evidence at >= 70%.
 */
final class RecallEvaluator
{
    private const MAX_POINTS = 5;

    public function __construct(
        private readonly PromptRunner $runner,
        private readonly RecallScorer $scorer,
        private readonly MasteryEvaluator $mastery,
    ) {}

    public function score(User $user, Concept $concept, string $answer, ?Lesson $lesson = null): RecallEvaluation
    {
        $rubric = $this->rubricFor($concept);
        $context = ['rubric' => $rubric, 'answer' => $answer];

        try {
            $result = $this->runner->run(
                $user,
                'recall',
                $this->scorer->system($rubric),
                $this->scorer->user($answer),
                $context,
                fn (string $content): bool => $this->parse($content) !== null,
            );

            $evaluation = $this->parse($result->content)
                ?? new RecallEvaluation(0.0, [], $rubric);
        } catch (BudgetExceeded $exceeded) {
            throw $exceeded;
        } catch (RuntimeException) {
            $evaluation = new RecallEvaluation(0.0, [], $rubric);
        }

        RecallAttempt::query()->create([
            'user_id' => $user->id,
            'concept_id' => $concept->id,
            'lesson_id' => $lesson?->id,
            'answer' => $answer,
            'score' => $evaluation->score,
            'evaluation' => [
                'rubric' => $rubric,
                'matched_points' => $evaluation->matchedPoints,
                'missing_points' => $evaluation->missingPoints,
            ],
        ]);

        if ($evaluation->passed()) {
            $this->mastery->promote($user, $concept, MasteryEvaluator::RECALL);
        }

        return $evaluation;
    }

    /**
     * Rubric = the concept's definition split into sentences (max 5); never
     * empty so scoring always has something to grade against.
     *
     * @return list<string>
     */
    private function rubricFor(Concept $concept): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($concept->definition));

        $points = $sentences === false
            ? []
            : array_values(array_filter(array_map('trim', $sentences), static fn (string $s): bool => $s !== ''));

        if ($points === []) {
            $points = [$concept->name];
        }

        return array_slice($points, 0, self::MAX_POINTS);
    }

    private function parse(string $content): ?RecallEvaluation
    {
        $raw = trim($content);

        if (preg_match('/\{.*\}/s', $raw, $match) === 1) {
            $raw = $match[0];
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['score']) || ! is_numeric($data['score'])) {
            return null;
        }

        $score = max(0.0, min(100.0, (float) $data['score']));

        return new RecallEvaluation(
            $score,
            $this->stringList($data['matched_points'] ?? []),
            $this->stringList($data['missing_points'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
