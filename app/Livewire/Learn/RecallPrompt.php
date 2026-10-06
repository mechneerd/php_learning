<?php

namespace App\Livewire\Learn;

use App\Models\Concept;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\BudgetExceeded;
use App\Services\Learning\RecallEvaluator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Explain-it input with rubric feedback (docs/11 EXPLAIN step): scores the
 * learner's own words, lists missing points, promotes `recall` evidence at
 * >= 70%.
 */
#[Title('Explain it')]
class RecallPrompt extends Component
{
    public Concept $concept;

    public ?Lesson $lesson = null;

    public string $answer = '';

    /**
     * @var array{score: float, matched_points: list<string>, missing_points: list<string>}|null
     */
    public ?array $evaluation = null;

    public ?string $notice = null;

    public function mount(Concept $concept, ?Lesson $lesson = null): void
    {
        $this->concept = $concept;
        $this->lesson = $lesson;
    }

    public function score(RecallEvaluator $evaluator): void
    {
        $this->validate([
            'answer' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->notice = null;

        try {
            $evaluation = $evaluator->score($user, $this->concept, $this->answer, $this->lesson);
        } catch (BudgetExceeded $exceeded) {
            $this->notice = 'Daily AI budget spent ('.$exceeded->used.'/'.$exceeded->budget
                .' tokens). Try again tomorrow.';

            return;
        }

        $this->evaluation = [
            'score' => $evaluation->score,
            'matched_points' => $evaluation->matchedPoints,
            'missing_points' => $evaluation->missingPoints,
        ];

        $this->notice = $evaluation->passed()
            ? 'Recall logged - this counts as evidence for your mastery.'
            : 'Not there yet - fill the missing points and try again.';
    }

    public function retry(): void
    {
        $this->answer = '';
        $this->evaluation = null;
        $this->notice = null;
    }

    public function render(): View
    {
        return view('livewire.learn.recall-prompt');
    }
}
