<?php

use App\Services\Learning\HintLadder;

it('keeps hint 1 available from the start', function () {
    $ladder = new HintLadder;

    expect($ladder->unlockedHintLevel(0, []))->toBe(1)
        ->and($ladder->canReveal(1, 0, 0, [], 0))->toBeTrue();
});

it('unlocks hint 2 only after hint 1 has been viewed', function () {
    $ladder = new HintLadder;

    expect($ladder->unlockedHintLevel(0, []))->toBe(1)
        ->and($ladder->unlockedHintLevel(0, [1]))->toBe(2)
        ->and($ladder->canReveal(2, 0, 0, [1], 0))->toBeTrue();
});

it('unlocks hint 3 after hint 2 is viewed and two attempts exist', function () {
    $ladder = new HintLadder;

    expect($ladder->unlockedHintLevel(1, [1, 2]))->toBe(2)
        ->and($ladder->unlockedHintLevel(2, [1, 2]))->toBe(3)
        ->and($ladder->canReveal(3, 2, 0, [1, 2], 0))->toBeTrue();
});

it('blocks the solution before level 3 plus 30 seconds', function () {
    $ladder = new HintLadder;

    expect($ladder->solutionUnlocked(0, [1, 2], 120))->toBeFalse()
        ->and($ladder->solutionUnlocked(0, [1, 2, 3], 29))->toBeFalse()
        ->and($ladder->canReveal(HintLadder::SOLUTION_LEVEL, 5, 0, [1, 2], 120))->toBeFalse();
});

it('unlocks the solution after level 3 is viewed and 30 seconds pass', function () {
    $ladder = new HintLadder;

    expect($ladder->solutionUnlocked(0, [1, 2, 3], HintLadder::SOLUTION_AFTER_SECONDS))->toBeTrue()
        ->and($ladder->canReveal(HintLadder::SOLUTION_LEVEL, 2, 0, [1, 2, 3], 45))->toBeTrue();
});

it('unlocks the solution after two failed attempts regardless of hints', function () {
    $ladder = new HintLadder;

    expect($ladder->solutionUnlocked(HintLadder::SOLUTION_AFTER_FAILURES, [], 0))->toBeTrue()
        ->and($ladder->canReveal(HintLadder::SOLUTION_LEVEL, 2, 2, [], 0))->toBeTrue()
        ->and($ladder->solutionUnlocked(1, [], 0))->toBeFalse();
});
