<?php

use App\Support\LaravelBridge;

test('forStage returns only the rows tagged for that stage', function () {
    $stage8 = LaravelBridge::forStage(8);

    expect($stage8)->not->toBeEmpty()
        ->and(array_column($stage8, 'core'))->toContain('Front Controller (Ch 12)')
        ->and(array_column($stage8, 'core'))->not->toContain('Observer (Ch 11)');

    $stage7 = LaravelBridge::forStage(7);

    expect(array_column($stage7, 'laravel'))->toContain('Jobs & Queues');
});

test('stages without bridge rows return an empty list', function () {
    expect(LaravelBridge::forStage(1))->toBe([])
        ->and(LaravelBridge::forStage(6))->toBe([]);
});
