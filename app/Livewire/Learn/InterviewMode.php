<?php

namespace App\Livewire\Learn;

use App\Enums\InterviewGrade;
use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Models\InterviewQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Interview mode (docs/08 §10): topic filter → question card → free-text
 * attempt → reveal the model answer + follow-ups → grade myself
 * (Confident / Shaky / Missed), which upserts a spaced review item.
 */
#[Title('Interview')]
class InterviewMode extends Component
{
    public ?string $topic = null;

    public ?int $questionId = null;

    public string $attempt = '';

    public bool $revealed = false;

    public function mount(): void
    {
        $this->questionId = $this->questionIds()->first();
    }

    public function selectTopic(?string $topic): void
    {
        $this->topic = $topic;

        $this->resetQuestion();
    }

    public function next(): void
    {
        $this->resetQuestion();
    }

    public function reveal(): void
    {
        $this->revealed = true;
    }

    public function grade(string $grade): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $interviewGrade = InterviewGrade::tryFrom($grade);

        if ($interviewGrade === null || $this->questionId === null || ! $this->revealed) {
            return;
        }

        $question = InterviewQuestion::query()->published()->find($this->questionId);

        if ($question === null) {
            return;
        }

        ReviewItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'item_type' => ReviewItemType::Interview,
                'item_id' => $question->id,
            ],
            [
                'concept_id' => $question->concept_id,
                'reason' => $interviewGrade->reason(),
                'due_at' => now()->addDays($interviewGrade->dueDelayDays()),
                'status' => ReviewItemStatus::Due,
            ],
        );

        $this->resetQuestion();
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $question = $this->questionId !== null
            ? InterviewQuestion::query()->published()->find($this->questionId)
            : null;

        if ($question !== null && $this->topic !== null && $question->topic !== $this->topic) {
            $question = null;
            $this->questionId = null;
        }

        $topics = InterviewQuestion::query()
            ->published()
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic');

        return view('livewire.learn.interview', [
            'question' => $question,
            'topics' => $topics,
        ]);
    }

    /**
     * Published question ids for the current topic filter (ordered).
     *
     * @return Collection<int, int>
     */
    private function questionIds(): Collection
    {
        return InterviewQuestion::query()
            ->published()
            ->when($this->topic !== null, fn ($query) => $query->where('topic', $this->topic))
            ->orderBy('ord')
            ->pluck('id');
    }

    private function resetQuestion(): void
    {
        $ids = $this->questionIds();

        if ($ids->isEmpty()) {
            $this->questionId = null;

            return;
        }

        $currentPosition = $ids->search($this->questionId);
        $nextPosition = $currentPosition === false ? 0 : ($currentPosition + 1) % $ids->count();

        $this->questionId = $ids[$nextPosition];
        $this->attempt = '';
        $this->revealed = false;
    }
}
