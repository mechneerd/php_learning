<?php

use App\Enums\HintGrade;
use App\Services\Learning\CardScheduler;

it('schedules the first exposure at one day for every grade', function () {
    $scheduler = new CardScheduler;

    expect($scheduler->schedule(0, HintGrade::Hard))->toBe(1)
        ->and($scheduler->schedule(0, HintGrade::Ok))->toBe(1)
        ->and($scheduler->schedule(0, HintGrade::Easy))->toBe(1);
});

it('multiplies the interval by the grade factor', function () {
    $scheduler = new CardScheduler;

    // hard: x1.2 -> round(1.2)=1, round(14.4)=14
    expect($scheduler->schedule(1, HintGrade::Hard))->toBe(1)
        ->and($scheduler->schedule(12, HintGrade::Hard))->toBe(14);

    // ok: x2.5 -> round(2.5)=3, round(7.5)=8
    expect($scheduler->schedule(1, HintGrade::Ok))->toBe(3)
        ->and($scheduler->schedule(3, HintGrade::Ok))->toBe(8);

    // easy: x4 -> 4, 12
    expect($scheduler->schedule(1, HintGrade::Easy))->toBe(4)
        ->and($scheduler->schedule(3, HintGrade::Easy))->toBe(12);
});

it('never schedules below one day and lapses reset to one day', function () {
    $scheduler = new CardScheduler;

    expect($scheduler->schedule(1, HintGrade::Hard))->toBeGreaterThanOrEqual(1)
        ->and($scheduler->lapse())->toBe(1);
});
