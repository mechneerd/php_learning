<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\CodeExample;
use App\Models\Lesson;
use App\Services\Ai\Generators\CodeExamplesGenerator;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: lesson pages -> tiered code examples plus their lesson
 * blocks (status in_review).
 */
class GenerateCodeExamplesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(CodeExamplesGenerator $generator, PipelineWriter $writer): void
    {
        $lesson = Lesson::query()->findOrFail($this->lessonId);

        $draft = $generator->generate($lesson);

        if ($draft === null) {
            return;
        }

        CodeExample::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', '!=', ContentStatus::Published->value)
            ->delete();

        $ord = 0;

        foreach ($draft->examples as $example) {
            $row = CodeExample::query()->create(array_merge($writer->provenance($lesson), [
                'lesson_id' => $lesson->id,
                'concept_id' => null,
                'listing_ref' => null,
                'tier' => $example['tier']->value,
                'title' => $example['title'],
                'code' => $example['code'],
                'expected_output' => $example['expected_output'],
                'explanation' => $example['explanation'],
                'syntax_notes' => null,
                'common_mistake' => null,
                'external_ref' => null,
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
            ]));

            $writer->insertBeforeRefs($lesson, BlockType::CodeExample, [
                'code_example_id' => $row->id,
            ], ProvenanceSource::Ai);
        }
    }
}
