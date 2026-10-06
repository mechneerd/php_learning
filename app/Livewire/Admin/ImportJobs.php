<?php

namespace App\Livewire\Admin;

use App\Enums\GenerationStatus;
use App\Jobs\DerivePrerequisitesJob;
use App\Jobs\DetectOutdatedJob;
use App\Jobs\GenerateCardsJob;
use App\Jobs\GenerateCodeExamplesJob;
use App\Jobs\GenerateDiagramJob;
use App\Jobs\GenerateExercisesJob;
use App\Jobs\GenerateQuizJob;
use App\Models\AiGeneration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * /admin/import-jobs - monitor Pipeline A: ai_generations table, import
 * jobs and the queue depth, with retry for failed generations
 * (docs/08 screen table).
 */
#[Title('Import Jobs')]
class ImportJobs extends Component
{
    public string $filter = 'all';

    public ?string $notice = null;

    public function retry(int $generationId): void
    {
        $generation = AiGeneration::query()->find($generationId);

        if ($generation === null || $generation->status !== GenerationStatus::Failed) {
            $this->notice = 'Only failed generations can be retried.';

            return;
        }

        $job = match ($generation->stage) {
            'exercise' => $generation->entity_id !== null ? new GenerateExercisesJob((int) $generation->entity_id) : null,
            'quiz' => $generation->entity_id !== null ? new GenerateQuizJob((int) $generation->entity_id) : null,
            'cards' => $generation->entity_id !== null ? new GenerateCardsJob((int) $generation->entity_id) : null,
            'diagram' => $generation->entity_id !== null ? new GenerateDiagramJob((int) $generation->entity_id) : null,
            'code_examples' => $generation->entity_id !== null ? new GenerateCodeExamplesJob((int) $generation->entity_id) : null,
            'outdated' => $generation->entity_id !== null ? new DetectOutdatedJob((int) $generation->entity_id) : null,
            'prerequisites' => new DerivePrerequisitesJob,
            default => null,
        };

        if ($job === null) {
            $this->notice = "'{$generation->stage}' retries run through content:generate for the owning stage.";

            return;
        }

        dispatch($job);

        $this->notice = "Retried '{$generation->stage}' generation (queued).";
    }

    public function clearFailed(): void
    {
        $count = AiGeneration::query()->where('status', GenerationStatus::Failed)->delete();

        $this->notice = "Cleared {$count} failed generation rows.";
    }

    public function render(): View
    {
        $generations = AiGeneration::query()
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest('id')
            ->limit(50)
            ->get();

        $failed = AiGeneration::query()->where('status', GenerationStatus::Failed)->count();
        $todayTokens = (int) AiGeneration::query()
            ->whereDate('created_at', today())
            ->selectRaw('COALESCE(SUM(tokens_in) + SUM(tokens_out), 0) as t')
            ->value('t');

        return view('livewire.admin.import-jobs', [
            'generations' => $generations,
            'failed' => $failed,
            'todayTokens' => $todayTokens,
            'budget' => (int) config('ai.daily_generation_budget'),
            'queued' => DB::table('jobs')->count(),
            'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
        ]);
    }
}
