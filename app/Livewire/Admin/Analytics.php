<?php

namespace App\Livewire\Admin;

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * /admin/analytics - content coverage per stage, exercise pass rates and
 * AI spend chart from ai_generations (docs/08 admin screen table).
 */
#[Title('Analytics')]
class Analytics extends Component
{
    /**
     * @var array<int, array{number:int, name:string, lessons_total:int, lessons_published:int, exercises_total:int, exercises_published:int}>
     */
    public array $stageRows = [];

    /**
     * @var array<int, array{id:int, prompt:string, attempts:int, correct:int, rate:int}>
     */
    public array $exerciseRows = [];

    /**
     * @var array<int, array{day:string, label:string, cost:float, pct:int, tokens:int}>
     */
    public array $spendDays = [];

    /**
     * @var array{stages:int, lessons_total:int, lessons_published:int, attempts:int, correct:int, pass_rate:int, spend:float, generations:int, failed:int}
     */
    public array $stats = [
        'stages' => 0,
        'lessons_total' => 0,
        'lessons_published' => 0,
        'attempts' => 0,
        'correct' => 0,
        'pass_rate' => 0,
        'spend' => 0.0,
        'generations' => 0,
        'failed' => 0,
    ];

    public function mount(): void
    {
        $this->stageRows = $this->buildStageRows();
        $this->exerciseRows = $this->buildExerciseRows();
        $this->spendDays = $this->buildSpendDays();
        $this->stats = $this->buildStats();
    }

    /**
     * @return array<int, array{number:int, name:string, lessons_total:int, lessons_published:int, exercises_total:int, exercises_published:int}>
     */
    private function buildStageRows(): array
    {
        $lessonCounts = Lesson::query()->toBase()
            ->selectRaw('stage_id, COUNT(*) as lessons_total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as lessons_published', [ContentStatus::Published->value])
            ->groupBy('stage_id')
            ->get()
            ->keyBy('stage_id');

        $exerciseCounts = Exercise::query()->toBase()
            ->join('lessons', 'lessons.id', '=', 'exercises.lesson_id')
            ->whereNull('lessons.deleted_at')
            ->selectRaw('lessons.stage_id as stage_id, COUNT(*) as exercises_total, SUM(CASE WHEN exercises.status = ? THEN 1 ELSE 0 END) as exercises_published', [ContentStatus::Published->value])
            ->groupBy('lessons.stage_id')
            ->get()
            ->keyBy('stage_id');

        $rows = [];
        foreach (Stage::query()->orderBy('number')->get() as $stage) {
            $lessons = $lessonCounts->get($stage->id);
            $exercises = $exerciseCounts->get($stage->id);

            $rows[] = [
                'number' => $stage->number,
                'name' => $stage->name,
                'lessons_total' => (int) ($lessons->lessons_total ?? 0),
                'lessons_published' => (int) ($lessons->lessons_published ?? 0),
                'exercises_total' => (int) ($exercises->exercises_total ?? 0),
                'exercises_published' => (int) ($exercises->exercises_published ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{id:int, prompt:string, attempts:int, correct:int, rate:int}>
     */
    private function buildExerciseRows(): array
    {
        $top = ExerciseAttempt::query()->toBase()
            ->selectRaw('exercise_id, COUNT(*) as attempts, SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as correct', [AttemptResult::Correct->value])
            ->groupBy('exercise_id')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        if ($top->isEmpty()) {
            return [];
        }

        $prompts = Exercise::query()
            ->whereIn('id', $top->pluck('exercise_id'))
            ->get(['id', 'prompt'])
            ->keyBy('id');

        $rows = [];
        foreach ($top as $row) {
            $exercise = $prompts->get((int) $row->exercise_id);
            if ($exercise === null) {
                continue;
            }

            $attempts = (int) $row->attempts;
            $correct = (int) $row->correct;

            $rows[] = [
                'id' => (int) $row->exercise_id,
                'prompt' => str($exercise->prompt)->limit(80)->toString(),
                'attempts' => $attempts,
                'correct' => $correct,
                'rate' => $attempts > 0 ? (int) round($correct / $attempts * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Cost per day for the last 30 days, gaps filled with zero.
     *
     * @return array<int, array{day:string, label:string, cost:float, pct:int, tokens:int}>
     */
    private function buildSpendDays(): array
    {
        $rows = AiGeneration::query()->toBase()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(tokens_in), 0) + COALESCE(SUM(tokens_out), 0) as tokens')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $max = 0.0;
        foreach ($rows as $row) {
            $max = max($max, (float) $row->cost);
        }

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $match = $rows->get($key);
            $cost = (float) ($match->cost ?? 0);

            $days[] = [
                'day' => $key,
                'label' => $date->format('d M'),
                'cost' => $cost,
                'pct' => $max > 0 ? (int) round($cost / $max * 100) : 0,
                'tokens' => (int) ($match->tokens ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return array{stages:int, lessons_total:int, lessons_published:int, attempts:int, correct:int, pass_rate:int, spend:float, generations:int, failed:int}
     */
    private function buildStats(): array
    {
        $lessonsTotal = 0;
        $lessonsPublished = 0;
        foreach ($this->stageRows as $row) {
            $lessonsTotal += $row['lessons_total'];
            $lessonsPublished += $row['lessons_published'];
        }

        $attemptsRow = ExerciseAttempt::query()->toBase()
            ->selectRaw('COUNT(*) as attempts, SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as correct', [AttemptResult::Correct->value])
            ->first();

        $attempts = (int) ($attemptsRow->attempts ?? 0);
        $correct = (int) ($attemptsRow->correct ?? 0);

        $generationsRow = AiGeneration::query()->toBase()
            ->selectRaw('COUNT(*) as generations, COALESCE(SUM(cost), 0) as cost, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [GenerationStatus::Failed->value])
            ->first();

        return [
            'stages' => count($this->stageRows),
            'lessons_total' => $lessonsTotal,
            'lessons_published' => $lessonsPublished,
            'attempts' => $attempts,
            'correct' => $correct,
            'pass_rate' => $attempts > 0 ? (int) round($correct / $attempts * 100) : 0,
            'spend' => (float) ($generationsRow->cost ?? 0),
            'generations' => (int) ($generationsRow->generations ?? 0),
            'failed' => (int) ($generationsRow->failed ?? 0),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.analytics');
    }
}
