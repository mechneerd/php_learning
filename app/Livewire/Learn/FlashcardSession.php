<?php

namespace App\Livewire\Learn;

use App\Enums\HintGrade;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Services\Learning\CardScheduler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FlashcardSession extends Component
{
    public const SESSION_SIZE = 20;

    /** @var list<int> */
    public array $queue = [];

    public int $index = 0;

    public bool $flipped = false;

    public int $reviewed = 0;

    /** @var array{hard: int, ok: int, easy: int}|null */
    public ?array $nextIntervals = null;

    public function mount(): void
    {
        $this->queue = $this->buildQueue();
    }

    public function flip(): void
    {
        $this->flipped = ! $this->flipped;
    }

    public function grade(string $grade): void
    {
        $hintGrade = HintGrade::tryFrom($grade);

        if ($hintGrade === null || ! $this->flipped) {
            return;
        }

        $cardId = $this->queue[$this->index] ?? null;

        if ($cardId === null) {
            return;
        }

        $scheduler = new CardScheduler;

        $existing = FlashcardReview::query()
            ->where('user_id', Auth::id())
            ->where('card_id', $cardId)
            ->first();

        $currentInterval = $existing->interval_days ?? 0;
        $next = $scheduler->schedule($currentInterval, $hintGrade);

        FlashcardReview::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'card_id' => $cardId],
            [
                'grade' => $hintGrade,
                'interval_days' => $next,
                'ease' => $existing->ease ?? CardScheduler::INITIAL_EASE,
                'next_review_at' => now()->addDays($next),
                'reviewed_at' => now(),
            ],
        );

        $this->reviewed++;
        $this->index++;
        $this->flipped = false;
        $this->nextIntervals = null;
    }

    public function render(): View
    {
        $cardId = $this->queue[$this->index] ?? null;
        $card = $cardId !== null ? Flashcard::query()->published()->find($cardId) : null;

        if ($card !== null && ! $this->flipped) {
            $existing = FlashcardReview::query()
                ->where('user_id', Auth::id())
                ->where('card_id', $card->id)
                ->first();
            $currentInterval = $existing->interval_days ?? 0;
            $scheduler = new CardScheduler;

            $this->nextIntervals = [
                'hard' => $scheduler->schedule($currentInterval, HintGrade::Hard),
                'ok' => $scheduler->schedule($currentInterval, HintGrade::Ok),
                'easy' => $scheduler->schedule($currentInterval, HintGrade::Easy),
            ];
        } else {
            $this->nextIntervals = null;
        }

        return view('livewire.learn.flashcards', [
            'card' => $card,
            'done' => $card === null,
            'remaining' => max(0, count($this->queue) - $this->index),
        ]);
    }

    /**
     * Due cards first, then unseen cards, up to the session size.
     *
     * @return list<int>
     */
    private function buildQueue(): array
    {
        $reviews = FlashcardReview::query()
            ->where('user_id', Auth::id())
            ->get();

        $due = $reviews
            ->filter(fn (FlashcardReview $review): bool => $review->next_review_at->isPast())
            ->pluck('card_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $reviewedIds = $reviews->pluck('card_id')->map(fn ($id): int => (int) $id)->all();

        $unseen = Flashcard::query()
            ->published()
            ->when(
                $reviewedIds !== [],
                fn ($query) => $query->whereNotIn('id', $reviewedIds),
            )
            ->orderBy('ord')
            ->limit(self::SESSION_SIZE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $queue = array_merge($due, $unseen);

        return array_values(array_slice($queue, 0, self::SESSION_SIZE));
    }
}
