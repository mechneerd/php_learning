<?php

namespace App\Services\Ai;

use App\Services\Learning\HintLadder;

/**
 * Hint-ladder policy for the tutor (docs/10 system policy rule 2 and the
 * flow in docs/09 section 9): a full solution needs ladder position >= 3 or
 * 2 failed attempts; otherwise reply with the next hint level only.
 */
final class HintPolicy
{
    public function __construct(private readonly HintLadder $ladder) {}

    /**
     * @param  list<int>  $givenLevels
     */
    public function solutionAllowed(int $failedAttempts, array $givenLevels, int $secondsOnExercise): bool
    {
        return $this->ladder->solutionUnlocked($failedAttempts, $givenLevels, $secondsOnExercise);
    }

    /**
     * The next hint level the tutor may hand out (1-3).
     *
     * @param  list<int>  $givenLevels
     */
    public function nextHintLevel(int $attempts, array $givenLevels): int
    {
        return $this->ladder->unlockedHintLevel($attempts, $givenLevels);
    }
}
