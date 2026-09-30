<?php

namespace App\Livewire\Learn;

use App\Enums\AttemptResult;
use App\Http\Requests\SubmitExerciseAttemptRequest;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseHint;
use App\Models\Lesson;
use App\Services\Learning\ExerciseGrader;
use App\Services\Learning\HintLadder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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

        $result = $grader->grade($exercise, $this->answer, $exercise->tests);

        ExerciseAttempt::query()->create([
            'user_id' => Auth::id(),
            'exercise_id' => $exercise->id,
            'code' => $this->answer,
            'result' => $result->result,
            'hints_used' => $this->hintsUsed(),
            'duration_sec' => $this->secondsOpen(),
            'test_results' => $result->testResultsArray(),
        ]);

        $this->grade = [
            'result' => $result->result->value,
            'label' => $result->result->label(),
            'badge' => $result->result->badgeClass(),
            'feedback' => $result->feedback,
            'tests' => $result->testResultsArray(),
        ];

        $this->loadHistory();
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
