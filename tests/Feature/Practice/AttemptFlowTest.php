<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\MasteryLevel;
use App\Livewire\Learn\PracticeRunner;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseTest;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\MasteryEvaluator;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->lesson = Lesson::factory()->create([
        'status' => ContentStatus::Published,
        'title' => 'Objects and classes',
    ]);

    $this->exercise = Exercise::factory()->create([
        'lesson_id' => $this->lesson->id,
        'type' => ExerciseType::Write,
        'prompt' => 'Build a Greeter class with a greeting method.',
        'starter_code' => "<?php\n\n// your code here\n",
    ]);

    ExerciseTest::query()->create([
        'exercise_id' => $this->exercise->id,
        'ord' => 1,
        'type' => 'assert_contains',
        'payload' => ['needle' => 'class Greeter {'],
        'weight' => 1,
    ]);

    $this->exercise->hints()->create(['level' => 1, 'text' => 'Start with the class keyword.']);
    $this->exercise->hints()->create(['level' => 2, 'text' => 'Name the class Greeter.']);
    $this->exercise->hints()->create(['level' => 3, 'text' => 'Add a public function greeting().']);
});

it('serves the lesson practice page with sandbox limits', function () {
    $this->get(route('practice.show', $this->lesson))
        ->assertOk()
        ->assertSee('Sandboxed runs')
        ->assertSee('Build a Greeter class');
});

it('serves the global practice page', function () {
    $this->get(route('practice'))->assertOk();
});

it('redirects guests to login', function () {
    auth()->guard()->logout();

    $this->get(route('practice.show', $this->lesson))->assertRedirect();
});

it('records a correct attempt and shows it in history', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', "<?php\nclass Greeter { public function greeting() { return 'hi'; } }")
        ->call('submit')
        ->assertSet('grade.result', AttemptResult::Correct->value)
        ->assertSee('Passed')
        ->assertSet('history.0.result', AttemptResult::Correct->value);

    $attempt = ExerciseAttempt::query()->sole();

    expect($attempt->user_id)->toBe($this->user->id)
        ->and($attempt->exercise_id)->toBe($this->exercise->id)
        ->and($attempt->result)->toBe(AttemptResult::Correct);
});

it('feeds a correct attempt into concept mastery', function () {
    $concept = Concept::factory()->create();
    $this->exercise->update(['concept_id' => $concept->id]);

    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', "<?php\nclass Greeter { public function greeting() { return 'hi'; } }")
        ->call('submit')
        ->assertSet('grade.result', AttemptResult::Correct->value);

    $mastery = ConceptMastery::query()->sole();

    expect($mastery->user_id)->toBe($this->user->id)
        ->and($mastery->concept_id)->toBe($concept->id)
        ->and($mastery->level)->toBe(MasteryLevel::Practicing)
        ->and($mastery->evidence)->toHaveKey(MasteryEvaluator::EASY);
});

it('records a failed attempt when the static check misses', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php class Greeter2 {}')
        ->call('submit')
        ->assertSet('grade.result', AttemptResult::Incorrect->value)
        ->assertSee('Failed');

    expect(ExerciseAttempt::query()->sole()->result)->toBe(AttemptResult::Incorrect);
});

it('rejects an empty answer without recording an attempt', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '')
        ->call('submit')
        ->assertHasErrors('answer');

    expect(ExerciseAttempt::query()->count())->toBe(0);
});

it('blocks banned functions as an error attempt', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php eval($code); class Greeter {}')
        ->call('submit')
        ->assertSet('grade.result', AttemptResult::Error->value)
        ->assertSee('eval');

    expect(ExerciseAttempt::query()->sole()->result)->toBe(AttemptResult::Error);
});

it('unlocks hints in order and blocks level 3 without attempts', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('revealHint', 1)
        ->assertSet('viewedHints', [1])
        ->call('revealHint', 2)
        ->assertSet('viewedHints', [1, 2]);

    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('revealHint', 2)
        ->assertStatus(403);

    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('revealHint', 3)
        ->assertStatus(403);
});

it('blocks the solution until two failed attempts exist', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('revealSolution')
        ->assertStatus(403);

    foreach (range(1, 2) as $i) {
        ExerciseAttempt::query()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'code' => '<?php nope;',
            'result' => AttemptResult::Incorrect,
            'hints_used' => 0,
            'duration_sec' => 10,
            'test_results' => [],
        ]);
    }

    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('revealSolution')
        ->assertSet('showSolution', true);
});

it('ignores selection of an exercise outside the lesson', function () {
    $foreign = Exercise::factory()->create();

    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->call('select', $foreign->id)
        ->assertSet('exerciseId', $this->exercise->id);
});

it('resets the editor back to the starter code', function () {
    Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php edited();')
        ->call('resetAnswer')
        ->assertSet('answer', "<?php\n\n// your code here\n")
        ->assertSet('grade', null);
});
