<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Services\Ai\Generators\CardsGenerator;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: lesson -> flashcards plus the card_refs block
 * (status in_review).
 */
class GenerateCardsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(CardsGenerator $generator, PipelineWriter $writer): void
    {
        $lesson = Lesson::query()->findOrFail($this->lessonId);

        $conceptSlugs = [];
        foreach ($lesson->concepts()->get() as $concept) {
            $conceptSlugs[] = $concept->slug;
        }

        $draft = $generator->generate($lesson, $conceptSlugs);

        if ($draft === null) {
            return;
        }

        Flashcard::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', '!=', ContentStatus::Published->value)
            ->delete();

        $ord = 0;
        $labels = [];

        foreach ($draft->cards as $card) {
            Flashcard::query()->create(array_merge($writer->provenance($lesson), [
                'concept_id' => null,
                'lesson_id' => $lesson->id,
                'card_type' => $card['card_type'],
                'front' => $card['front'],
                'back' => $card['back'],
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
            ]));

            $labels[] = mb_substr($card['front'], 0, 70);
        }

        $writer->insertBeforeRefs($lesson, BlockType::CardRefs, [
            'labels' => $labels,
        ], ProvenanceSource::Ai);
    }
}
