<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Exercise;
use App\Models\ExerciseHint;
use App\Models\Lesson;
use App\Services\Ai\Generators\ExercisesGenerator;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: lesson -> exercises with hint ladders and static-grade
 * answers, plus the exercise_ref block (status in_review).
 */
class GenerateExercisesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(ExercisesGenerator $generator, PipelineWriter $writer): void
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

        Exercise::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', '!=', ContentStatus::Published->value)
            ->delete();

        $ord = 0;
        $labels = [];
        $ids = [];

        foreach ($draft->exercises as $item) {
            $exercise = Exercise::query()->create(array_merge($writer->provenance($lesson), [
                'lesson_id' => $lesson->id,
                'concept_id' => null,
                'type' => $item['type'],
                'difficulty' => $item['difficulty'],
                'prompt' => $item['prompt'],
                'starter_code' => null,
                'solution_code' => null,
                'explanation' => null,
                'expected_answer' => is_array($item['answer']) ? $item['answer'] : [$item['answer']],
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
            ]));

            foreach ($item['hints'] as $level => $hint) {
                ExerciseHint::query()->create([
                    'exercise_id' => $exercise->id,
                    'level' => $level + 1,
                    'text' => $hint,
                ]);
            }

            $ids[] = $exercise->id;
            $labels[] = mb_substr($item['prompt'], 0, 70);
        }

        $writer->insertBeforeRefs($lesson, BlockType::ExerciseRef, [
            'labels' => $labels,
            'ids' => $ids,
        ], ProvenanceSource::Ai);
    }
}
