<?php

namespace App\Services\Learning;

/**
 * Unlock rules for the four-level hint ladder.
 *
 * L1 Conceptual: always available.
 * L2 Syntax: after L1 has been viewed.
 * L3 Implementation: after L2 has been viewed AND 2 attempts have been made.
 * Solution: after 2 failed attempts, OR after L3 has been viewed and 30 seconds
 * have passed on the exercise.
 */
final class HintLadder
{
    public const SOLUTION_AFTER_SECONDS = 30;

    public const SOLUTION_AFTER_FAILURES = 2;

    public const HINT3_AFTER_ATTEMPTS = 2;

    public const SOLUTION_LEVEL = 4;

    /**
     * Highest hint level (1-3) the learner may reveal.
     *
     * @param  list<int>  $viewedLevels  hint levels already revealed
     */
    public function unlockedHintLevel(int $attempts, array $viewedLevels): int
    {
        if (! in_array(1, $viewedLevels, true)) {
            return 1;
        }

        if (! in_array(2, $viewedLevels, true)) {
            return 2;
        }

        if ($attempts < self::HINT3_AFTER_ATTEMPTS) {
            return 2;
        }

        return 3;
    }

    /**
     * @param  list<int>  $viewedLevels
     */
    public function solutionUnlocked(int $failedAttempts, array $viewedLevels, int $secondsOnExercise): bool
    {
        if ($failedAttempts >= self::SOLUTION_AFTER_FAILURES) {
            return true;
        }

        return in_array(3, $viewedLevels, true)
            && $secondsOnExercise >= self::SOLUTION_AFTER_SECONDS;
    }

    /**
     * Can the learner reveal this rung? 1-3 = hints, 4 = solution.
     *
     * @param  list<int>  $viewedLevels
     */
    public function canReveal(int $level, int $attempts, int $failedAttempts, array $viewedLevels, int $secondsOnExercise): bool
    {
        if ($level === self::SOLUTION_LEVEL) {
            return $this->solutionUnlocked($failedAttempts, $viewedLevels, $secondsOnExercise);
        }

        return $level >= 1 && $level <= $this->unlockedHintLevel($attempts, $viewedLevels);
    }
}
