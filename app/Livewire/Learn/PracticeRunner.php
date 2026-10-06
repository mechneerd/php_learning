<?php

namespace App\Livewire\Learn;

use App\Enums\AttemptResult;
use App\Enums\RunStatus;
use App\Http\Requests\SubmitExerciseAttemptRequest;
use App\Jobs\RunCodeJob;
use App\Models\CodeRun;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseHint;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Execution\BannedFunctionCheck;
use App\Services\Learning\ExerciseGrader;
use App\Services\Learning\HintLadder;
use App\Services\Learning\MasteryEvaluator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class PracticeRunner extends Component
{
    public ?int $lessonId = null;

    public int $exerciseId = 0;

    public string $answer = '';

    /** @var list<int> */
    public array $viewedHints = [];

    public int $openedAt = 0;

    /** @var array<string, mixed> */
    public ?array $grade = null;

    /** @var array<int, array<string, mixed>> */
    public array $history = [];

    public bool $showSolution = false;

    public ?int $runId = null;

    /** @var array<string, mixed>|null */
    public ?array $liveRun = null;

    public string $runNotice = '';

    public function mount(?Lesson $lesson = null): void
    {
        $this->lessonId = $lesson?->id;
        $this->openExercise($this->defaultExerciseId());
    }

    public function select(int $exerciseId): void
    {
        if (in_array($exerciseId, $this->exerciseIds(), true)) {
            $this->openExercise($exerciseId);
        }
    }

    public function next(): void
    {
        $ids = $this->exerciseIds();
        $position = array_search($this->exerciseId, $ids, true);

        if ($position !== false && isset($ids[$position + 1])) {
            $this->openExercise($ids[$position + 1]);
        }
    }

    public function revealHint(int $level, HintLadder $ladder): void
    {
        $stats = $this->attemptStats();

        abort_unless($ladder->canReveal(
            $level,
            $stats['attempts'],
            $stats['failed'],
            $this->viewedHints,
            $this->secondsOpen(),
        ), 403);

        if ($level < 1 || $level > HintLadder::SOLUTION_LEVEL) {
            abort(403);
        }

        if ($level === HintLadder::SOLUTION_LEVEL) {
            $this->showSolution = true;

            return;
        }

        if (! in_array($level, $this->viewedHints, true)) {
            $this->viewedHints[] = $level;
            sort($this->viewedHints);
        }
    }

    public function revealSolution(HintLadder $ladder): void
    {
        $stats = $this->attemptStats();

        abort_unless($ladder->solutionUnlocked(
            $stats['failed'],
            $this->viewedHints,
            $this->secondsOpen(),
        ), 403);

        $this->showSolution = true;
    }

    public function submit(ExerciseGrader $grader): void
    {
        $this->validate(SubmitExerciseAttemptRequest::formRules());

        $exercise = Exercise::query()
            ->published()
            ->with('tests')
            ->findOrFail($this->exerciseId);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $liveRun = CodeRun::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('status', RunStatus::Done)
            ->where('code', $this->answer)
            ->orderByDesc('id')
            ->first();

        $result = $grader->grade($exercise, $this->answer, $exercise->tests, $liveRun?->stdout);

        DB::transaction(function () use ($exercise, $result, $user): void {
            ExerciseAttempt::query()->create([
                'user_id' => Auth::id(),
                'exercise_id' => $exercise->id,
                'code' => $this->answer,
                'result' => $result->result,
                'hints_used' => $this->hintsUsed(),
                'duration_sec' => $this->secondsOpen(),
                'test_results' => $result->testResultsArray(),
            ]);

            (new MasteryEvaluator)->recordAttempt(
                $user,
                $exercise,
                $result->result,
            );
        });

        $this->grade = [
            'result' => $result->result->value,
            'label' => $result->result->label(),
            'badge' => $result->result->badgeClass(),
            'feedback' => $result->feedback,
            'tests' => $result->testResultsArray(),
            'live' => $liveRun !== null,
        ];

        $this->loadHistory();
    }

    /**
     * Queues a sandbox run of the current answer (docs/12 §2). Pre-checks
     * run before dispatch (fast fail); the runner repeats them.
     */
    public function run(): void
    {
        $this->validate([
            'answer' => ['required', 'string', 'max:'.(int) config('runner.limits.source_bytes')],
        ]);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $exercise = Exercise::query()->published()->findOrFail($this->exerciseId);
        $this->runNotice = '';

        if (! $exercise->type->isCode()) {
            $this->runNotice = 'Run is only available for code exercises.';

            return;
        }

        $key = 'run:'.$user->id;
        $max = (int) config('runner.rate_limit.max');
        $decay = (int) config('runner.rate_limit.decay_seconds');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->runNotice = 'Run limit reached — try again in '.RateLimiter::availableIn($key).' seconds.';

            return;
        }

        RateLimiter::hit($key, $decay);

        $banned = app(BannedFunctionCheck::class)->firstHit($this->answer);

        if ($banned !== null) {
            $this->storeBlockedRun($user, $exercise, "Blocked: `{$banned}` is not allowed (execution safety rules).");
            $this->runNotice = "Blocked: `{$banned}` is not allowed in the sandbox.";

            return;
        }

        $pending = CodeRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [RunStatus::Queued->value, RunStatus::Running->value])
            ->where('created_at', '>=', now()->subSeconds(60))
            ->count();

        if ($pending >= (int) config('runner.max_pending_per_user')) {
            $this->runNotice = 'You already have '.$pending.' run(s) in progress — wait for them to finish.';

            return;
        }

        $global = (int) Cache::get('runs:global:pending', 0);

        if ($global >= (int) config('runner.max_pending_global')) {
            $this->runNotice = 'The sandbox is busy right now — try again in a few seconds.';

            return;
        }

        Cache::put('runs:global:pending', $global + 1, now()->addSeconds(60));

        $run = CodeRun::query()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'attempt_id' => (string) Str::uuid(),
            'code' => $this->answer,
            'status' => RunStatus::Queued,
        ]);

        RunCodeJob::dispatch($run->id);

        $this->runId = $run->id;
        $this->syncRun();
    }

    /**
     * Reloads the current run for the live result panel (wire:poll).
     */
    public function syncRun(): void
    {
        if ($this->runId === null) {
            $this->liveRun = null;

            return;
        }

        $run = CodeRun::query()
            ->where('user_id', Auth::id())
            ->find($this->runId);

        if ($run === null) {
            $this->liveRun = null;

            return;
        }

        $metrics = $run->metrics ?? [];

        $this->liveRun = [
            'status' => $run->status->value,
            'label' => $run->status->label(),
            'badge' => $run->status->badgeClass(),
            'pending' => $run->status->isPending(),
            'stdout' => $run->stdout ?? '',
            'stderr' => $run->stderr ?? '',
            'exit_code' => $run->exit_code,
            'duration_ms' => $run->duration_ms,
            'truncated' => (bool) ($metrics['truncated'] ?? false),
            'error' => $run->error,
        ];
    }

    private function storeBlockedRun(User $user, Exercise $exercise, string $reason): void
    {
        $run = CodeRun::query()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'attempt_id' => (string) Str::uuid(),
            'code' => $this->answer,
            'status' => RunStatus::Blocked,
            'error' => $reason,
        ]);

        $this->runId = $run->id;
        $this->syncRun();
    }

    public function resetAnswer(): void
    {
        $exercise = Exercise::query()->findOrFail($this->exerciseId);
        $this->answer = $exercise->starter_code ?? '';
        $this->grade = null;
        $this->dispatch('code-reset', answer: $this->answer);
    }

    public function render(): View
    {
        $exercise = $this->exerciseId > 0
            ? Exercise::query()->published()->find($this->exerciseId)
            : null;

        $ladder = app(HintLadder::class);
        $stats = $this->attemptStats();

        $hints = $exercise?->hints?->map(fn (ExerciseHint $hint): array => [
            'level' => $hint->level,
            'text' => $hint->text,
            'revealed' => in_array($hint->level, $this->viewedHints, true),
            'unlocked' => $ladder->canReveal(
                $hint->level,
                $stats['attempts'],
                $stats['failed'],
                $this->viewedHints,
                $this->secondsOpen(),
            ),
        ])->values()->all() ?? [];

        return view('livewire.learn.practice', [
            'lesson' => $this->lessonId !== null ? Lesson::query()->find($this->lessonId) : null,
            'exercises' => $this->exercises(),
            'exercise' => $exercise,
            'hints' => $hints,
            'solutionUnlocked' => $ladder->solutionUnlocked(
                $stats['failed'],
                $this->viewedHints,
                $this->secondsOpen(),
            ),
            'attemptsCount' => $stats['attempts'],
            'failedCount' => $stats['failed'],
            'runPending' => (bool) ($this->liveRun['pending'] ?? false),
            'runNotice' => $this->runNotice,
            'liveRun' => $this->liveRun,
        ]);
    }

    /** @return Collection<int, Exercise> */
    private function exercises(): Collection
    {
        $query = Exercise::query()->published()->orderBy('ord');

        if ($this->lessonId !== null) {
            $query->where('lesson_id', $this->lessonId);
        }

        return $query->get();
    }

    /** @return list<int> */
    private function exerciseIds(): array
    {
        $ids = [];

        foreach ($this->exercises() as $exercise) {
            $ids[] = $exercise->id;
        }

        return $ids;
    }

    private function defaultExerciseId(): int
    {
        $ids = $this->exerciseIds();

        return $ids[0] ?? 0;
    }

    private function openExercise(int $exerciseId): void
    {
        $this->exerciseId = $exerciseId;
        $exercise = Exercise::query()->find($exerciseId);
        $this->answer = $exercise->starter_code ?? '';
        $this->grade = null;
        $this->showSolution = false;
        $this->viewedHints = [];
        $this->openedAt = time();
        $this->runId = null;
        $this->liveRun = null;
        $this->runNotice = '';
        $this->loadHistory();
    }

    private function loadHistory(): void
    {
        $this->history = ExerciseAttempt::query()
            ->where('user_id', Auth::id())
            ->where('exercise_id', $this->exerciseId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (ExerciseAttempt $attempt): array => [
                'result' => $attempt->result->value,
                'label' => $attempt->result->label(),
                'badge' => $attempt->result->badgeClass(),
                'hints_used' => $attempt->hints_used,
                'duration_sec' => $attempt->duration_sec,
                'at' => $attempt->created_at->format('H:i'),
            ])
            ->all();
    }

    /** @return array{attempts: int, failed: int} */
    private function attemptStats(): array
    {
        $attempts = ExerciseAttempt::query()
            ->where('user_id', Auth::id())
            ->where('exercise_id', $this->exerciseId)
            ->get(['result']);

        $failed = $attempts->filter(
            fn (ExerciseAttempt $attempt): bool => $attempt->result !== AttemptResult::Correct
        )->count();

        return ['attempts' => $attempts->count(), 'failed' => $failed];
    }

    private function secondsOpen(): int
    {
        return $this->openedAt === 0 ? 0 : max(0, time() - $this->openedAt);
    }

    private function hintsUsed(): int
    {
        $highest = $this->viewedHints === [] ? 0 : max($this->viewedHints);

        return $this->showSolution ? max($highest, HintLadder::SOLUTION_LEVEL) : $highest;
    }
}
