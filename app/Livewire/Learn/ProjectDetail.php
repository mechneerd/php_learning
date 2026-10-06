<?php

namespace App\Livewire\Learn;

use App\Enums\ContentStatus;
use App\Enums\MasteryLevel;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskCheck;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Project detail (docs/08 §11): brief, requirements checklist, tasks with
 * hints, and the solution gated behind the first checked task ("available
 * after 1 attempt"). Tasks stay hidden while prerequisites are not
 * `comfortable`.
 */
#[Title('Project')]
class ProjectDetail extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless($project->status === ContentStatus::Published, 404);

        $this->project = $project;
    }

    public function toggleTask(int $taskId): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if (! $this->project->isUnlockedFor($user)) {
            return;
        }

        $task = ProjectTask::query()
            ->whereKey($taskId)
            ->where('project_id', $this->project->id)
            ->firstOrFail();

        $existing = ProjectTaskCheck::query()
            ->where('user_id', $user->id)
            ->where('project_task_id', $task->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        ProjectTaskCheck::query()->create([
            'user_id' => $user->id,
            'project_task_id' => $task->id,
        ]);
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $project = $this->project;
        $unlocked = $project->isUnlockedFor($user);
        $progress = $project->progressFor($user);

        $taskIds = $project->tasks->pluck('id');
        $checkedIds = $unlocked
            ? ProjectTaskCheck::query()
                ->where('user_id', $user->id)
                ->whereIn('project_task_id', $taskIds)
                ->pluck('project_task_id')
                ->all()
            : [];

        $conceptRows = [];

        foreach ($project->requiredConceptSlugs() as $slug) {
            $concept = Concept::query()->where('slug', $slug)->first();

            $level = MasteryLevel::Unseen;

            if ($concept !== null) {
                $mastery = ConceptMastery::query()
                    ->where('user_id', $user->id)
                    ->where('concept_id', $concept->id)
                    ->first();

                $level = $mastery->level ?? MasteryLevel::Unseen;
            }

            $conceptRows[] = [
                'slug' => $slug,
                'name' => $concept->name ?? $slug,
                'level' => $level,
                'met' => $level->atLeast(MasteryLevel::Comfortable),
            ];
        }

        return view('livewire.learn.project-detail', [
            'unlocked' => $unlocked,
            'progress' => $progress,
            'checkedIds' => $checkedIds,
            'tasks' => $unlocked ? $project->tasks : collect(),
            'conceptRows' => $conceptRows,
            'solutionVisible' => $unlocked && $progress['checked'] >= 1,
            'requirements' => $project->requirements ?? [],
        ]);
    }
}
