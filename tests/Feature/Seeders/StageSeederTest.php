<?php

use App\Enums\StageSource;
use App\Models\Stage;
use Database\Seeders\StageSeeder;

it('seeds the twelve teaching stages', function () {
    (new StageSeeder)->run();

    expect(Stage::count())->toBe(12)
        ->and(Stage::where('number', 0)->first()->source)->toBe(StageSource::Ai)
        ->and(Stage::where('number', 2)->first()->source)->toBe(StageSource::Book)
        ->and(Stage::where('number', 3)->first()->source)->toBe(StageSource::Mixed);
});

it('is idempotent', function () {
    (new StageSeeder)->run();
    (new StageSeeder)->run();

    expect(Stage::count())->toBe(12)
        ->and(Stage::where('slug', 'object-core')->exists())->toBeTrue();
});

it('seeds gate rules on exactly the six gated stages', function () {
    (new StageSeeder)->run();

    $gated = Stage::whereNotNull('gate_rules')->orderBy('number')->pluck('number')->all();

    expect($gated)->toBe([2, 4, 5, 8, 9, 11])
        ->and(Stage::where('number', 2)->first()->gate_rules[0])
        ->toBe(['quiz' => 'php-foundations', 'min_score' => 80])
        ->and(Stage::where('number', 4)->first()->gate_rules)->toHaveCount(4)
        ->and(Stage::where('number', 11)->first()->gate_rules)
        ->toBe([['comfortable_pct' => 60], ['mastered_pct' => 20]]);
});
