<?php

namespace App\Livewire\Learn;

use App\Enums\ReviewItemType;
use App\Models\Concept;
use App\Models\ErrorPattern;
use App\Models\Exercise;
use App\Models\Flashcard;
use App\Models\InterviewQuestion;
use App\Models\Lesson;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Learning\ReviewScheduler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The revision queue (docs/08 screen 7): due items grouped by kind with
 * Snooze 1d / Mark done / Open per item.
 */
#[Title('Revision')]
class RevisionQueue extends Component
{
    public function snooze(int $itemId): void
    {
        $item = $this->findOwned($itemId);

        if ($item === null) {
            return;
        }

        $item->snooze(1);
    }

    public function markDone(int $itemId): void
    {
        $item = $this->findOwned($itemId);

        if ($item === null) {
            return;
        }

        $item->complete();
    }

    public function render(ReviewScheduler $scheduler): View
    {
        $user = $this->user();

        $scheduler->syncDue($user);
        $items = $scheduler->dueItems($user);

        return view('livewire.learn.revision', [
            'groups' => $this->groupItems($items),
            'total' => $items->count(),
            'nextDueAt' => $scheduler->nextDueAt($user),
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function findOwned(int $itemId): ?ReviewItem
    {
        return ReviewItem::query()
            ->whereKey($itemId)
            ->where('user_id', auth()->id())
            ->first();
    }

    /**
     * Group due items by kind (Cards / Concepts / Exercises / Errors /
     * Lessons) with display titles and open links resolved.
     *
     * @param  Collection<int, ReviewItem>  $items
     * @return list<array{type: ReviewItemType, label: string, items: list<array{id: int, title: string, detail: string, reason: string, url: string|null, concept: Concept|null}>}>
     */
    private function groupItems($items): array
    {
        $cardIds = [];
        $conceptIds = [];
        $exerciseIds = [];
        $lessonIds = [];
        $errorIds = [];
        $interviewIds = [];

        foreach ($items as $item) {
            match ($item->item_type) {
                ReviewItemType::Card => $cardIds[] = $item->item_id,
                ReviewItemType::Concept => $conceptIds[] = $item->item_id,
                ReviewItemType::Exercise => $exerciseIds[] = $item->item_id,
                ReviewItemType::Lesson => $lessonIds[] = $item->item_id,
                ReviewItemType::Error => $errorIds[] = $item->item_id,
                ReviewItemType::Interview => $interviewIds[] = $item->item_id,
            };
        }

        $cards = Flashcard::query()->whereIn('id', $cardIds)->get(['id', 'front', 'back'])->keyBy('id');
        $concepts = Concept::query()->whereIn('id', $conceptIds)->get(['id', 'slug', 'name', 'definition'])->keyBy('id');
        $exercises = Exercise::query()->with('lesson:id,slug,title')->whereIn('id', $exerciseIds)->get()->keyBy('id');
        $lessons = Lesson::query()->whereIn('id', $lessonIds)->get(['id', 'slug', 'title'])->keyBy('id');
        $errors = ErrorPattern::query()->whereIn('id', $errorIds)->get(['id', 'slug', 'name', 'symptom'])->keyBy('id');
        $questions = InterviewQuestion::query()->whereIn('id', $interviewIds)->get(['id', 'question', 'topic'])->keyBy('id');

        /** @var array<string, list<array{id: int, title: string, detail: string, reason: string, url: string|null, concept: Concept|null}>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $row = match ($item->item_type) {
                ReviewItemType::Card => $this->cardRow($item, $cards),
                ReviewItemType::Concept => $this->conceptRow($item, $concepts),
                ReviewItemType::Exercise => $this->exerciseRow($item, $exercises),
                ReviewItemType::Lesson => $this->lessonRow($item, $lessons),
                ReviewItemType::Error => $this->errorRow($item, $errors),
                ReviewItemType::Interview => $this->interviewRow($item, $questions),
            };

            $row['reason'] = $item->reason;
            $grouped[$item->item_type->value][] = $row;
        }

        $groups = [];

        foreach (ReviewItemType::cases() as $type) {
            if (! isset($grouped[$type->value])) {
                continue;
            }

            $groups[] = [
                'type' => $type,
                'label' => $type->label().'s',
                'items' => $grouped[$type->value],
            ];
        }

        return $groups;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Flashcard>  $cards
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function cardRow(ReviewItem $item, $cards): array
    {
        $card = $cards->get($item->item_id);

        return [
            'id' => $item->id,
            'title' => $card !== null ? $card->front : 'Card #'.$item->item_id,
            'detail' => $card !== null ? mb_substr((string) $card->back, 0, 80) : '',
            'url' => route('flashcards'),
            'concept' => null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Concept>  $concepts
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function conceptRow(ReviewItem $item, $concepts): array
    {
        $concept = $concepts->get($item->item_id);

        return [
            'id' => $item->id,
            'title' => $concept !== null ? $concept->name : 'Concept #'.$item->item_id,
            'detail' => $concept !== null ? mb_substr((string) $concept->definition, 0, 80) : '',
            'url' => $concept !== null ? route('concepts.show', $concept->slug) : null,
            'concept' => $concept,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Exercise>  $exercises
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function exerciseRow(ReviewItem $item, $exercises): array
    {
        $exercise = $exercises->get($item->item_id);
        $slug = $exercise?->lesson?->slug;

        return [
            'id' => $item->id,
            'title' => $exercise !== null ? mb_substr((string) $exercise->prompt, 0, 80) : 'Exercise #'.$item->item_id,
            'detail' => $exercise?->type->label() ?? '',
            'url' => $slug !== null ? route('practice.show', $slug) : null,
            'concept' => null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Lesson>  $lessons
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function lessonRow(ReviewItem $item, $lessons): array
    {
        $lesson = $lessons->get($item->item_id);

        return [
            'id' => $item->id,
            'title' => $lesson !== null ? $lesson->title : 'Lesson #'.$item->item_id,
            'detail' => '',
            'url' => $lesson !== null ? route('lessons.show', $lesson->slug) : null,
            'concept' => null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ErrorPattern>  $errors
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function errorRow(ReviewItem $item, $errors): array
    {
        $error = $errors->get($item->item_id);

        return [
            'id' => $item->id,
            'title' => $error !== null ? $error->name : 'Error #'.$item->item_id,
            'detail' => $error !== null ? mb_substr((string) $error->symptom, 0, 80) : '',
            'url' => $error !== null ? route('errors.show', $error->slug) : null,
            'concept' => null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, InterviewQuestion>  $questions
     * @return array{id: int, title: string, detail: string, url: string|null, concept: Concept|null}
     */
    private function interviewRow(ReviewItem $item, $questions): array
    {
        $question = $questions->get($item->item_id);

        return [
            'id' => $item->id,
            'title' => $question !== null ? mb_substr((string) $question->question, 0, 80) : 'Question #'.$item->item_id,
            'detail' => $question !== null ? ucfirst($question->topic) : '',
            'url' => route('interview'),
            'concept' => null,
        ];
    }
}
