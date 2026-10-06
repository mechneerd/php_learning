<?php

namespace App\Livewire\Learn;

use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The projects screen (docs/08 §11): cards grouped by level with unlock
 * state (locked = required concepts not yet `comfortable`).
 */
#[Title('Projects')]
class Projects extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        /** @var list<array{level: ProjectLevel, projects: list<array{model: Project, unlocked: bool, checked: int, total: int}>}> $levels */
        $levels = [];

        foreach (ProjectLevel::cases() as $level) {
            $projects = Project::query()
                ->published()
                ->where('level', $level)
                ->orderBy('ord')
                ->with('tasks')
                ->get();

            if ($projects->isEmpty()) {
                continue;
            }

            $levels[] = [
                'level' => $level,
                'projects' => $projects->map(function (Project $project) use ($user): array {
                    $progress = $project->progressFor($user);

                    return [
                        'model' => $project,
                        'unlocked' => $project->isUnlockedFor($user),
                        'checked' => $progress['checked'],
                        'total' => $progress['total'],
                    ];
                })->all(),
            ];
        }

        return view('livewire.learn.projects', [
            'levels' => $levels,
        ]);
    }
}
