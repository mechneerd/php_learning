<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\RunStatus;
use App\Livewire\Learn\PracticeRunner;
use App\Models\CodeRun;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseTest;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->exercise = Exercise::factory()->create([
        'lesson_id' => $this->lesson->id,
        'type' => ExerciseType::Write,
        'starter_code' => "<?php\n",
    ]);

    ExerciseTest::query()->create([
        'exercise_id' => $this->exercise->id,
        'ord' => 1,
        'type' => 'assert_output',
        'payload' => ['expected' => 'Hello'],
        'weight' => 1,
    ]);

    ExerciseTest::query()->create([
        'exercise_id' => $this->exercise->id,
        'ord' => 2,
        'type' => 'assert_contains',
        'payload' => ['needle' => 'echo'],
        'weight' => 1,
    ]);

    config(['runner.secret' => 'test-secret']);
});

function fakeRunnerOk(): void
{
    Http::fake(['*' => Http::response([
        'status' => 'ok',
        'stdout' => "Hello\n",
        'stderr' => '',
        'exit_code' => 0,
        'duration_ms' => 8,
        'truncated' => false,
        'oom' => false,
        'error' => null,
    ])]);
}

it('runs the answer in the sandbox and grades it against the live output', function () {
    fakeRunnerOk();

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php echo "Hello";')
        ->call('run');

    $component->assertSet('liveRun.status', RunStatus::Done->value)
        ->assertSet('liveRun.stdout', "Hello\n")
        ->assertSet('liveRun.pending', false);

    $run = CodeRun::query()->firstOrFail();
    expect($run->status)->toBe(RunStatus::Done)
        ->and($run->user_id)->toBe($this->user->id)
        ->and($run->attempt_id)->not->toBe('')
        ->and($run->exit_code)->toBe(0)
        ->and($run->duration_ms)->toBe(8);

    $component->call('submit');

    $component->assertSet('grade.result', AttemptResult::Correct->value)
        ->assertSet('grade.live', true);

    $attempt = ExerciseAttempt::query()->firstOrFail();
    expect($attempt->result)->toBe(AttemptResult::Correct);
});

it('blocks banned code before dispatching to the runner', function () {
    Http::fake();

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php eval($userInput);')
        ->call('run');

    $component->assertSet('runNotice', fn (string $notice): bool => str_contains($notice, 'Blocked'))
        ->assertSet('liveRun.status', RunStatus::Blocked->value);

    $run = CodeRun::query()->firstOrFail();
    expect($run->status)->toBe(RunStatus::Blocked)
        ->and($run->error)->toContain('eval');
    Http::assertNothingSent();
});

it('shows a friendly failure when the runner is unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php echo "Hello";')
        ->call('run');

    $component->assertSet('liveRun.status', RunStatus::Failed->value)
        ->assertSet('liveRun.error', fn (?string $error): bool => $error !== null && str_contains($error, 'not available'));
});

it('refuses to run non-code exercises', function () {
    Http::fake();
    $this->exercise->forceFill(['type' => ExerciseType::PredictOutput])->save();

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', 'It prints 42')
        ->call('run');

    $component->assertSet('runNotice', 'Run is only available for code exercises.');
    expect(CodeRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('keeps assert_output pending when the code was never run', function () {
    fakeRunnerOk();

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', '<?php echo "Hello";')
        ->call('submit');

    $component->assertSet('grade.live', false)
        ->assertSet('grade.tests.0.status', 'pending')
        ->assertSee('Run your code');
});
