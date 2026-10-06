<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Lesson;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Services\Ai\Generators\QuizGenerator;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: lesson -> quiz questions with options, plus the quiz_ref
 * block (status in_review).
 */
class GenerateQuizJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(QuizGenerator $generator, PipelineWriter $writer): void
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

        QuizQuestion::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', '!=', ContentStatus::Published->value)
            ->delete();

        $ord = 0;
        $labels = [];
        $ids = [];

        foreach ($draft->questions as $item) {
            $question = QuizQuestion::query()->create(array_merge($writer->provenance($lesson), [
                'lesson_id' => $lesson->id,
                'concept_id' => null,
                'type' => $item['type'],
                'stem' => $item['question'],
                'explanation' => $item['explanation'],
                'difficulty' => 'medium',
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
            ]));

            foreach ($item['options'] as $index => $option) {
                QuizOption::query()->create([
                    'question_id' => $question->id,
                    'text' => $option,
                    'is_correct' => $index === $item['correct'],
                    'feedback' => null,
                    'ord' => $index + 1,
                ]);
            }

            $ids[] = $question->id;
            $labels[] = mb_substr($item['question'], 0, 70);
        }

        $writer->insertBeforeRefs($lesson, BlockType::QuizRef, [
            'labels' => $labels,
            'ids' => $ids,
        ], ProvenanceSource::Ai);
    }
}
