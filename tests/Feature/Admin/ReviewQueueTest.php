<?php

use App\Enums\AiActor;
use App\Enums\ContentStatus;
use App\Livewire\Admin\ReviewQueue;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\User;
use Livewire\Livewire;

it('approves an in-review lesson: published, versioned and off the queue', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::InReview]);

    $this->actingAs($admin);

    $component = Livewire::test(ReviewQueue::class)
        ->call('select', 'lesson', $lesson->id)
        ->assertSet('selectedType', 'lesson')
        ->assertSet('selectedId', $lesson->id)
        ->call('approve')
        ->assertDontSee($lesson->title);

    $lesson->refresh();

    expect($lesson->status)->toBe(ContentStatus::Published)
        ->and($lesson->reviewed_by)->toBe($admin->id)
        ->and($lesson->reviewed_at)->not->toBeNull();

    $version = ContentVersion::sole();

    expect($version->entity_type)->toBe('lesson')
        ->and($version->entity_id)->toBe($lesson->id)
        ->and($version->actor)->toBe(AiActor::Admin)
        ->and($version->payload['lines'])->toBeArray()->not->toBeEmpty();
});

it('rejects a draft back to draft status without a version snapshot', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::InReview]);

    $this->actingAs($admin);

    Livewire::test(ReviewQueue::class)
        ->call('select', 'lesson', $lesson->id)
        ->call('reject')
        ->assertDontSee($lesson->title);

    $lesson->refresh();

    expect($lesson->status)->toBe(ContentStatus::Draft)
        ->and($lesson->reviewed_by)->toBeNull()
        ->and(ContentVersion::query()->count())->toBe(0);
});

it('lists in-review lessons and ignores published ones', function () {
    $admin = User::factory()->admin()->create();
    $waiting = Lesson::factory()->create(['status' => ContentStatus::InReview, 'title' => 'Awaiting review marker']);
    Lesson::factory()->create(['status' => ContentStatus::Published, 'title' => 'Already live marker']);

    $this->actingAs($admin);

    Livewire::test(ReviewQueue::class)
        ->assertSee('Awaiting review marker')
        ->assertDontSee('Already live marker');

    expect($waiting->exists())->toBeTrue();
});

it('refuses learners', function () {
    $learner = User::factory()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::InReview]);

    $this->actingAs($learner);

    Livewire::test(ReviewQueue::class)
        ->call('select', 'lesson', $lesson->id)
        ->call('approve');

    expect($lesson->refresh()->status)->toBe(ContentStatus::InReview)
        ->and(ContentVersion::query()->count())->toBe(0);
});
