<?php

use App\Enums\MasteryLevel;
use App\Services\Learning\GateEvaluator;
use App\Services\Learning\GateEvidence;

it('treats a stage without rules as ungated', function () {
    $result = (new GateEvaluator)->evaluate(null, GateEvidence::empty());

    expect($result->checks)->toBe([])
        ->and($result->passed())->toBeTrue()
        ->and($result->isGated())->toBeFalse();
});

it('fails a concept rule when the level is missing', function () {
    $result = (new GateEvaluator)->evaluate(
        [['concept' => 'inheritance', 'min_level' => 'practicing']],
        GateEvidence::empty(),
    );

    $check = $result->checks[0];

    expect($result->passed())->toBeFalse()
        ->and($check->label)->toBe('inheritance >= practicing')
        ->and($check->have)->toBe('unseen')
        ->and($check->need)->toBe('practicing');
});

it('measures levels inclusively at the boundary', function () {
    $evaluator = new GateEvaluator;
    $rules = [['concept' => 'inheritance', 'min_level' => 'practicing']];

    $atBoundary = $evaluator->evaluate(
        $rules,
        new GateEvidence(levels: ['inheritance' => MasteryLevel::Practicing]),
    );
    $below = $evaluator->evaluate(
        $rules,
        new GateEvidence(levels: ['inheritance' => MasteryLevel::Learning]),
    );

    expect($atBoundary->passed())->toBeTrue()
        ->and($below->passed())->toBeFalse();
});

it('scores quizzes against the required percentage', function () {
    $evaluator = new GateEvaluator;
    $rules = [['quiz' => 'php-foundations', 'min_score' => 80]];

    $failing = $evaluator->evaluate($rules, GateEvidence::empty());
    $passing = $evaluator->evaluate($rules, new GateEvidence(quizScores: ['php-foundations' => 85]));

    expect($failing->passed())->toBeFalse()
        ->and($failing->checks[0]->have)->toBe('0%')
        ->and($failing->checks[0]->need)->toBe('80%')
        ->and($passing->passed())->toBeTrue();
});

it('counts correct exercises', function () {
    $evaluator = new GateEvaluator;
    $rules = [['exercises' => 10]];

    $failing = $evaluator->evaluate($rules, GateEvidence::empty());
    $passing = $evaluator->evaluate($rules, new GateEvidence(correctExercises: 10));

    expect($failing->passed())->toBeFalse()
        ->and($failing->checks[0]->label)->toBe('10 exercises correct')
        ->and($passing->passed())->toBeTrue();
});

it('checks comfort and mastery percentages', function () {
    $evaluator = new GateEvaluator;
    $rules = [['comfortable_pct' => 60], ['mastered_pct' => 20]];

    $allFailed = $evaluator->evaluate($rules, GateEvidence::empty());
    $half = $evaluator->evaluate($rules, new GateEvidence(comfortablePct: 60, masteredPct: 5));

    expect($allFailed->passed())->toBeFalse()
        ->and($allFailed->passedCount())->toBe(0)
        ->and($half->passed())->toBeFalse()
        ->and($half->passedCount())->toBe(1)
        ->and($half->failed()[0]->label)->toBe('20% of concepts mastered');
});

it('requires every rule of a gate to pass', function () {
    $result = (new GateEvaluator)->evaluate(
        [
            ['concept' => 'interface', 'min_level' => 'practicing'],
            ['quiz' => 'php-foundations', 'min_score' => 80],
        ],
        new GateEvidence(levels: ['interface' => MasteryLevel::Comfortable]),
    );

    expect($result->passed())->toBeFalse()
        ->and($result->passedCount())->toBe(1)
        ->and($result->failed())->toHaveCount(1);
});

it('rejects an unknown rule shape', function () {
    (new GateEvaluator)->evaluate([['nonsense' => true]], GateEvidence::empty());
})->throws(InvalidArgumentException::class);
