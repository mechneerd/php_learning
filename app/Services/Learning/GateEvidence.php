<?php

namespace App\Services\Learning;

use App\Enums\MasteryLevel;

/**
 * Everything a stage gate can check against. Phase 3 ships the shape with
 * empty evidence (no quiz/exercise/mastery tables yet); Phase 4/5 fill it.
 */
final readonly class GateEvidence
{
    /**
     * @param  array<string, MasteryLevel>  $levels  concept slug => level
     * @param  array<string, int>  $quizScores  quiz key => best score (%)
     * @param  int  $correctExercises  exercises ever answered correctly
     * @param  int  $comfortablePct  % of tracked concepts at comfortable+
     * @param  int  $masteredPct  % of tracked concepts mastered
     */
    public function __construct(
        public array $levels = [],
        public array $quizScores = [],
        public int $correctExercises = 0,
        public int $comfortablePct = 0,
        public int $masteredPct = 0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function levelFor(string $slug): MasteryLevel
    {
        return $this->levels[$slug] ?? MasteryLevel::Unseen;
    }

    public function scoreFor(string $quiz): int
    {
        return $this->quizScores[$quiz] ?? 0;
    }
}
