<?php

use App\Enums\ProgressState;
use App\Livewire\Learn\LessonView;
use App\Models\LessonProgress;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\LessonFixture;

it('opens a progress row when the lesson mounts', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson]);

    $progress = LessonProgress::where('user_id', $user->id)
        ->where('lesson_id', $lesson->id)
        ->first();

    expect($progress)->not->toBeNull()
        ->and($progress->state)->toBe(ProgressState::Opened)
        ->and($progress->active_seconds)->toBe(0)
        ->and($progress->opened_at)->not->toBeNull();
});

it('accumulates fifteen second focus ticks', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $this->actingAs($user);

    $component = Livewire::test(LessonView::class, ['lesson' => $lesson]);

    $component->dispatch('lesson-tick');
    expect(LessonProgress::firstOrFail()->active_seconds)->toBe(15);

    $component->dispatch('lesson-tick');
    expect(LessonProgress::firstOrFail()->active_seconds)->toBe(30);
});

it('upgrades to read after sixty active seconds and never downgrades', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $this->actingAs($user);

    $component = Livewire::test(LessonView::class, ['lesson' => $lesson]);

    foreach (range(1, 4) as $ignored) {
        $component->dispatch('lesson-tick');
    }

    expect(LessonProgress::firstOrFail()->active_seconds)->toBe(60)
        ->and(LessonProgress::firstOrFail()->state)->toBe(ProgressState::Read);

    $component->dispatch('lesson-tick');

    expect(LessonProgress::firstOrFail()->active_seconds)->toBe(75)
        ->and(LessonProgress::firstOrFail()->state)->toBe(ProgressState::Read);
});

it('tracks focus per user and lesson', function () {
    $lesson = LessonFixture::full();

    $this->actingAs(User::factory()->create());

    Livewire::test(LessonView::class, ['lesson' => $lesson])->dispatch('lesson-tick');

    expect(LessonProgress::count())->toBe(1);
});
