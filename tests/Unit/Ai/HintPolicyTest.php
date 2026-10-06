<?php

use App\Services\Ai\HintPolicy;
use App\Services\Learning\HintLadder;

it('refuses a solution below the ladder position', function () {
    $policy = new HintPolicy(new HintLadder);

    expect($policy->solutionAllowed(0, [], 0))->toBeFalse()
        ->and($policy->solutionAllowed(1, [], 0))->toBeFalse()
        ->and($policy->solutionAllowed(0, [1, 2], 0))->toBeFalse();
});

it('allows a solution after two failed attempts', function () {
    $policy = new HintPolicy(new HintLadder);

    expect($policy->solutionAllowed(2, [], 0))->toBeTrue()
        ->and($policy->solutionAllowed(3, [1], 0))->toBeTrue();
});

it('allows a solution once the ladder is complete and 30 seconds passed', function () {
    $policy = new HintPolicy(new HintLadder);

    expect($policy->solutionAllowed(0, [1, 2, 3], 29))->toBeFalse()
        ->and($policy->solutionAllowed(0, [1, 2, 3], 30))->toBeTrue();
});

it('hands out hint levels in order', function () {
    $policy = new HintPolicy(new HintLadder);

    expect($policy->nextHintLevel(0, []))->toBe(1)
        ->and($policy->nextHintLevel(0, [1]))->toBe(2)
        ->and($policy->nextHintLevel(1, [1, 2]))->toBe(2)
        ->and($policy->nextHintLevel(2, [1, 2]))->toBe(3);
});
