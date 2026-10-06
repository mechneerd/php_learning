<?php

use App\Enums\ContentStatus;
use App\Models\Concept;
use App\Models\ContentVersion;
use App\Models\Diagram;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Content\ContentApprover;

it('publishes a provenance-lite concept without review columns', function () {
    $admin = User::factory()->admin()->create();
    $concept = Concept::factory()->create(['status' => ContentStatus::InReview]);

    $entity = app(ContentApprover::class)->approve('concept', $concept->id, $admin);

    expect($entity->status)->toBe(ContentStatus::Published)
        ->and(ContentVersion::query()
            ->where('entity_type', 'concept')
            ->where('entity_id', $concept->id)
            ->exists())->toBeTrue();
});

it('rejects a concept back to draft', function () {
    $concept = Concept::factory()->create(['status' => ContentStatus::InReview]);

    $entity = app(ContentApprover::class)->reject('concept', $concept->id);

    expect($entity->fresh()?->status)->toBe(ContentStatus::Draft);
});

it('records the reviewer on full-provenance entities', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::InReview]);

    $entity = app(ContentApprover::class)->approve('lesson', $lesson->id, $admin);

    expect($entity->status)->toBe(ContentStatus::Published)
        ->and($entity->reviewed_by)->toBe($admin->id)
        ->and($entity->reviewed_at)->not->toBeNull();
});

it('clears the reviewer on reject for full-provenance entities', function () {
    $admin = User::factory()->admin()->create();
    $lesson = Lesson::factory()->create([
        'status' => ContentStatus::InReview,
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $entity = app(ContentApprover::class)->reject('lesson', $lesson->id);

    expect($entity->fresh()?->reviewed_by)->toBeNull()
        ->and($entity->fresh()?->reviewed_at)->toBeNull()
        ->and($entity->fresh()?->status)->toBe(ContentStatus::Draft);
});

it('publishes a diagram with the review columns added by migration', function () {
    $admin = User::factory()->admin()->create();
    $diagram = Diagram::factory()->create(['status' => ContentStatus::InReview]);

    $entity = app(ContentApprover::class)->approve('diagram', $diagram->id, $admin);

    expect($entity->status)->toBe(ContentStatus::Published)
        ->and($entity->reviewed_by)->toBe($admin->id)
        ->and($entity->reviewed_at)->not->toBeNull();
});
