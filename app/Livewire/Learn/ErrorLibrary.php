<?php

namespace App\Livewire\Learn;

use App\Enums\ContentStatus;
use App\Models\ErrorPattern;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Error library (docs/03 FR-30): the list groups patterns by category and
 * the detail page walks the six-step flow — what happened, why, identify,
 * fix, prevent, practice.
 */
#[Title('Errors')]
class ErrorLibrary extends Component
{
    public ?ErrorPattern $pattern = null;

    public function mount(?ErrorPattern $pattern = null): void
    {
        if ($pattern !== null) {
            abort_unless($pattern->status === ContentStatus::Published, 404);
        }

        $this->pattern = $pattern;
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if ($this->pattern !== null) {
            return view('livewire.learn.errors', [
                'pattern' => $this->pattern,
                'grouped' => [],
            ]);
        }

        $grouped = [];

        foreach (ErrorPattern::query()->published()->orderBy('category')->orderBy('name')->get() as $pattern) {
            $grouped[$pattern->category->value]['label'] = $pattern->category->label();
            $grouped[$pattern->category->value]['badge'] = $pattern->category->badgeClass();
            $grouped[$pattern->category->value]['patterns'][] = $pattern;
        }

        return view('livewire.learn.errors', [
            'pattern' => null,
            'grouped' => $grouped,
        ]);
    }
}
