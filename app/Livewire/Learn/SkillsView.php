<?php

namespace App\Livewire\Learn;

use App\Models\User;
use App\Services\Learning\SkillAggregator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The skills screen (docs/08 screen 9): 16 domains expandable to
 * concepts with level chips and evidence counts. Reading never raises
 * skill levels.
 */
#[Title('Skills')]
class SkillsView extends Component
{
    public ?string $expanded = null;

    public function toggle(string $domain): void
    {
        $this->expanded = $this->expanded === $domain ? null : $domain;
    }

    public function render(SkillAggregator $aggregator): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return view('livewire.learn.skills', [
            'rows' => $aggregator->aggregate($user),
            'expanded' => $this->expanded,
        ]);
    }
}
