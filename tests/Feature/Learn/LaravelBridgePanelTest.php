<?php

use App\Enums\ContentStatus;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;

test('a lesson in a bridged stage shows the laravel bridge panel', function () {
    $this->actingAs(User::factory()->create());

    $stage = Stage::factory()->create(['number' => 2, 'slug' => 'object-core']);
    $lesson = Lesson::factory()->create([
        'stage_id' => $stage->id,
        'status' => ContentStatus::Published,
        'title' => 'Classes and objects',
    ]);

    $this->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertSee('PHP → Laravel bridge')
        ->assertSee('Controllers, Services, Models')
        ->assertSee('app/Exceptions, report/render');
});

test('a lesson without bridge rows omits the panel', function () {
    $this->actingAs(User::factory()->create());

    $stage = Stage::factory()->create(['number' => 6, 'slug' => 'structural-patterns']);
    $lesson = Lesson::factory()->create([
        'stage_id' => $stage->id,
        'status' => ContentStatus::Published,
    ]);

    $this->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertDontSee('Laravel bridge');
});
