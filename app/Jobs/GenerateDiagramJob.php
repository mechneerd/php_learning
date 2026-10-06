<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\DiagramSource;
use App\Enums\ProvenanceSource;
use App\Models\Diagram;
use App\Models\Lesson;
use App\Services\Ai\Generators\DiagramGenerator;
use App\Services\Content\MermaidSanitizer;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Pipeline A: lesson -> one Mermaid diagram plus its lesson block
 * (status in_review; unrenderable Mermaid fails the job visibly).
 */
class GenerateDiagramJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(DiagramGenerator $generator, PipelineWriter $writer, MermaidSanitizer $sanitizer): void
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

        $source = $sanitizer->sanitize($draft->mermaidSource);

        if (! $sanitizer->isRenderable($source)) {
            throw new RuntimeException("Generated diagram for lesson {$lesson->id} is not renderable Mermaid.");
        }

        Diagram::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', '!=', ContentStatus::Published->value)
            ->delete();

        $diagram = Diagram::query()->create([
            'lesson_id' => $lesson->id,
            'kind' => $draft->kind,
            'title' => $draft->title,
            'mermaid_source' => $source,
            'source' => DiagramSource::Ai,
            'figure_ref' => null,
            'page_pdf' => null,
            'status' => ContentStatus::InReview,
            'ai_model' => (string) config('ai.model_quality'),
            'ai_generated_at' => now(),
            'ai_prompt_version' => (string) config('ai.prompt_versions.diagram', 'v1'),
            'is_outdated' => false,
        ]);

        $writer->insertBeforeRefs($lesson, BlockType::Diagram, [
            'diagram_id' => $diagram->id,
        ], ProvenanceSource::Ai);
    }
}
