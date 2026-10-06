<?php

use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\RunStatus;
use App\Livewire\Learn\PracticeRunner;
use App\Models\CodeRun;
use App\Models\Exercise;
use App\Models\ExerciseTest;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->exercise = Exercise::factory()->create([
        'lesson_id' => $this->lesson->id,
        'type' => ExerciseType::Write,
        'starter_code' => "<?php echo 'hi';\n",
    ]);

    ExerciseTest::query()->create([
        'exercise_id' => $this->exercise->id,
        'ord' => 1,
        'type' => 'assert_output',
        'payload' => ['expected' => 'hi'],
        'weight' => 1,
    ]);

    Http::fake(['*' => Http::response([
        'status' => 'ok',
        'stdout' => 'hi',
        'stderr' => '',
        'exit_code' => 0,
        'duration_ms' => 5,
        'truncated' => false,
        'oom' => false,
        'error' => null,
    ])]);

    config([
        'runner.secret' => 'test-secret',
        'runner.rate_limit.max' => 3,
        'runner.rate_limit.decay_seconds' => 60,
    ]);
});

it('rejects runs over the per-minute rate limit', function () {
    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson]);

    foreach ([1, 2, 3] as $attempt) {
        $component->set('answer', "<?php echo 'hi'; // {$attempt}")->call('run');
    }

    $component->set('answer', "<?php echo 'hi'; // 4")->call('run');

    $component->assertSet('runNotice', fn (string $notice): bool => str_contains($notice, 'Run limit reached'));
    expect(CodeRun::query()->count())->toBe(3);
});

it('refuses new runs while the user already has pending runs', function () {
    config(['runner.rate_limit.max' => 60]);

    foreach ([1, 2] as $i) {
        CodeRun::query()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'attempt_id' => (string) Str::uuid(),
            'code' => "<?php echo 'pending {$i}';",
            'status' => RunStatus::Queued,
        ]);
    }

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', "<?php echo 'hi';")
        ->call('run');

    $component->assertSet('runNotice', fn (string $notice): bool => str_contains($notice, 'in progress'));
    expect(CodeRun::query()->where('status', RunStatus::Queued)->count())->toBe(2);
});

it('refuses new runs while the global sandbox is saturated', function () {
    config(['runner.rate_limit.max' => 60]);
    Cache::put('runs:global:pending', 8, now()->addMinute());

    $component = Livewire::test(PracticeRunner::class, ['lesson' => $this->lesson])
        ->set('answer', "<?php echo 'hi';")
        ->call('run');

    $component->assertSet('runNotice', fn (string $notice): bool => str_contains($notice, 'sandbox is busy'));
    expect(CodeRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});
