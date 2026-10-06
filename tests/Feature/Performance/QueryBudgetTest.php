<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\LessonFixture;

/**
 * Executable eager-load audit: renders each heavy learner screen and
 * fails if the query count exceeds the agreed budget (docs/15 Phase 10).
 */
it('keeps this learner page within its query budget', function (string $target, int $budget) {
    $lesson = LessonFixture::full();
    $user = User::factory()->create();

    $uri = $target === 'lesson' ? route('lessons.show', $lesson->slug) : $target;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get($uri)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual($budget, "{$target} ran {$count} queries (budget {$budget})");
})->with([
    ['/dashboard', 45],
    ['/path', 25],
    ['/revision', 20],
    ['/skills', 45],
    ['/projects', 10],
    ['lesson', 20],
]);
