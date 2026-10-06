<?php

use App\Enums\MasteryLevel;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\User;
use App\Services\Ai\ContextAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LessonFixture;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('assembles the learner, lesson and history payload', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $mastered = Concept::factory()->create();
    ConceptMastery::factory()->mastered()->create([
        'user_id' => $user->id,
        'concept_id' => $mastered->id,
    ]);

    $learning = Concept::factory()->create();
    ConceptMastery::factory()->create([
        'user_id' => $user->id,
        'concept_id' => $learning->id,
        'level' => MasteryLevel::Learning,
    ]);

    $payload = (new ContextAssembler)->assemble($user, $lesson, 'teach');

    expect($payload['mode'])->toBe('teach')
        ->and($payload['current'])->toBeArray()
        ->and($payload['current']['lesson'])->toBe($lesson->slug)
        ->and($payload['current']['citation'])->toContain('Ch. 3')
        ->and($payload['learner']['mastered'])->toContain($mastered->slug)
        ->and($payload['learner']['weak'])->toContain($learning->slug)
        ->and($payload['history'])->toHaveKeys(['last_attempts', 'quiz_scores']);
});

test('leaves current null without an anchored lesson', function () {
    $payload = (new ContextAssembler)->assemble(User::factory()->create(), null);

    expect($payload['current'])->toBeNull()
        ->and($payload['mode'])->toBe('tutor');
});

test('trims the payload to the character ceiling', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $payload = (new ContextAssembler(charCeiling: 600))->assemble($user, $lesson);

    expect(strlen((string) json_encode($payload)))->toBeLessThanOrEqual(600);
});
