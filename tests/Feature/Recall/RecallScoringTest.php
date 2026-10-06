<?php

use App\Livewire\Learn\RecallPrompt;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\RecallAttempt;
use App\Models\User;
use App\Services\Learning\RecallEvaluation;
use App\Services\Learning\RecallEvaluator;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->concept = Concept::factory()->create([
        'definition' => 'A constructor runs when an object is created. It sets up the initial state of the object.',
    ]);
});

test('scores a complete explanation and promotes recall evidence', function () {
    $answer = 'A constructor runs when an object is created. It sets up the initial state of the object.';

    $evaluation = app(RecallEvaluator::class)->score($this->user, $this->concept, $answer);

    expect($evaluation->score)->toBeGreaterThanOrEqual(RecallEvaluation::PASS_SCORE)
        ->and($evaluation->missingPoints)->toBe([])
        ->and($evaluation->passed())->toBeTrue();

    $mastery = ConceptMastery::query()
        ->where('user_id', $this->user->id)
        ->where('concept_id', $this->concept->id)
        ->sole();

    expect($mastery->evidence)->toHaveKey('recall')
        ->and(RecallAttempt::query()->count())->toBe(1);
});

test('lists missing points for a weak answer without promoting', function () {
    $answer = 'I think it has something to do with starting things up in PHP maybe somehow?';

    $evaluation = app(RecallEvaluator::class)->score($this->user, $this->concept, $answer);

    expect($evaluation->score)->toBeLessThan(RecallEvaluation::PASS_SCORE)
        ->and($evaluation->missingPoints)->not->toBe([]);

    expect(ConceptMastery::query()->count())->toBe(0);

    $attempt = RecallAttempt::query()->sole();

    expect($attempt->evaluation)->toHaveKey('missing_points')
        ->and($attempt->evaluation['missing_points'])->not->toBe([]);
});

test('scores through the recall prompt component', function () {
    Livewire::test(RecallPrompt::class, ['concept' => $this->concept])
        ->set('answer', 'A constructor runs when an object is created. It sets up the initial state of the object.')
        ->call('score')
        ->assertSet('evaluation.score', 100.0)
        ->assertSee('You covered')
        ->assertSee('Recall logged');
});

test('shows missing points in the UI for a weak answer', function () {
    Livewire::test(RecallPrompt::class, ['concept' => $this->concept])
        ->set('answer', 'Honestly I am not sure what this concept is about at all right now.')
        ->call('score')
        ->assertSee('Missing points')
        ->assertSee('Not there yet');
});

test('requires a real explanation', function () {
    Livewire::test(RecallPrompt::class, ['concept' => $this->concept])
        ->set('answer', 'too short')
        ->call('score')
        ->assertHasErrors('answer');

    expect(RecallAttempt::query()->count())->toBe(0);
});
