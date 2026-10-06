<?php

namespace App\Jobs;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Stage;
use App\Services\Ai\Generators\LessonGenerator;
use App\Services\Content\BlockValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Pipeline A: section excerpt + concepts -> a full-template lesson with
 * status in_review. Dispatches the dependent content jobs when it lands
 * (docs/10 job chain).
 */
class GenerateLessonJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $sectionId,
        public readonly int $stageId,
        public readonly bool $force = false,
    ) {}

    public function handle(
        LessonGenerator $generator,
        BlockValidator $validator,
    ): void {
        $section = Section::query()->with('chapter')->findOrFail($this->sectionId);
        $chapter = $section->chapter;

        if ($chapter === null) {
            throw new RuntimeException("Section {$section->id} has no chapter.");
        }

        $stage = Stage::query()->findOrFail($this->stageId);

        $existing = Lesson::query()->where('slug', $this->slug($section))->first();

        if ($existing !== null && $existing->status === ContentStatus::Published && ! $this->force) {
            return;
        }

        [$conceptSlugs, $prereqSlugs] = $this->context($chapter->id);

        $draft = $generator->generate($section, $chapter, $conceptSlugs, $prereqSlugs);

        if ($draft === null) {
            return;
        }

        $lesson = $existing ?? new Lesson(['slug' => $this->slug($section)]);

        $lesson->forceFill([
            'stage_id' => $stage->id,
            'chapter_id' => $chapter->id,
            'section_id' => $section->id,
            'title' => $section->title,
            'summary' => $draft->summary,
            'status' => ContentStatus::InReview,
            'est_minutes' => $draft->estMinutes,
            'ord' => $lesson->ord ?? $this->nextOrd($stage->id),
            'source' => ProvenanceSource::Ai,
            'page_printed_from' => $section->page_printed_from,
            'page_printed_to' => $section->page_printed_to,
            'page_pdf_from' => $section->page_pdf_from,
            'page_pdf_to' => $section->page_pdf_to,
            'ai_model' => (string) config('ai.model_quality'),
            'ai_generated_at' => now(),
            'ai_prompt_version' => (string) config('ai.prompt_versions.lesson', 'v1'),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'is_outdated' => false,
        ])->save();

        $this->writeBlocks($validator, $lesson, $section, $draft->blocks);
        $this->linkConcepts($lesson, $draft->summary, $chapter->id);

        foreach ([
            GenerateCodeExamplesJob::class,
            GenerateDiagramJob::class,
            GenerateExercisesJob::class,
            GenerateQuizJob::class,
            GenerateCardsJob::class,
            DetectOutdatedJob::class,
        ] as $job) {
            $job::dispatch($lesson->id);
        }
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function context(int $chapterId): array
    {
        $lessonIds = Lesson::query()->where('chapter_id', $chapterId)->pluck('id');

        $concepts = Concept::query()
            ->whereHas('lessons', static fn ($q) => $q->whereIn('lesson_id', $lessonIds))
            ->get();

        $slugs = [];
        foreach ($concepts as $concept) {
            $slugs[] = $concept->slug;
        }

        $prereqs = [];

        foreach ($concepts as $concept) {
            foreach ($concept->prerequisites as $prereq) {
                if (! in_array($prereq->slug, $slugs, true)) {
                    $prereqs[$prereq->slug] = true;
                }
            }
        }

        return [$slugs, array_keys($prereqs)];
    }

    private function slug(Section $section): string
    {
        return 'ch'.$section->chapter()->value('number').'-'.$section->slug;
    }

    private function nextOrd(int $stageId): int
    {
        return ((int) Lesson::query()->where('stage_id', $stageId)->max('ord')) + 1;
    }

    /**
     * @param  list<array{type: string, payload: array<string, mixed>}>  $blocks
     */
    private function writeBlocks(BlockValidator $validator, Lesson $lesson, Section $section, array $blocks): void
    {
        $lesson->blocks()->delete();
        $ord = 0;

        foreach ($blocks as $block) {
            $type = BlockType::from($block['type']);
            $payload = $block['payload'];

            // Child jobs append the concrete example/diagram; empty reference
            // blocks are filled in when the practice entities land.
            if (in_array($type, [BlockType::CodeExample, BlockType::Diagram], true)) {
                continue;
            }

            if (in_array($type, [BlockType::ExerciseRef, BlockType::QuizRef, BlockType::CardRefs, BlockType::InterviewRef], true)
                && (($payload['labels'] ?? []) === [])) {
                continue;
            }

            $payload = $type === BlockType::BookQuote
                ? ['text' => trim((string) ($payload['text'] ?? '')), 'attribution' => 'Matt Zandstra, PHP 8 Objects, Patterns and Practice']
                : $payload;

            $validated = $validator->validate($type, $payload);

            if ($validated === []) {
                continue;
            }

            $lesson->blocks()->create([
                'ord' => ++$ord,
                'type' => $type,
                'payload' => $validated,
                'source' => in_array($type, [BlockType::BookQuote, BlockType::CodeExample], true)
                    ? ProvenanceSource::Book
                    : ProvenanceSource::Ai,
                'page_printed_from' => $section->page_printed_from,
                'page_printed_to' => $section->page_printed_to,
                'page_pdf_from' => $section->page_pdf_from,
                'page_pdf_to' => $section->page_pdf_to,
            ]);
        }
    }

    private function linkConcepts(Lesson $lesson, string $summary, int $chapterId): void
    {
        $lessonIds = Lesson::query()->where('chapter_id', $chapterId)->pluck('id');

        $concepts = Concept::query()
            ->whereHas('lessons', static fn ($q) => $q->whereIn('lesson_id', $lessonIds))
            ->get();

        if ($concepts->isEmpty()) {
            return;
        }

        $text = $summary.' '.json_encode($lesson->blocks()->pluck('payload')->all());

        $ids = $this->matchingIds($text, $concepts);

        foreach ($ids as $conceptId) {
            $lesson->concepts()->syncWithoutDetaching([$conceptId => ['role' => 'core']]);
        }
    }

    /**
     * @param  Collection<int, Concept>  $concepts
     * @return list<int>
     */
    private function matchingIds(string $text, $concepts): array
    {
        $haystack = ' '.mb_strtolower($text).' ';
        $ids = [];

        foreach ($concepts as $concept) {
            if (str_contains($haystack, mb_strtolower($concept->slug))
                || str_contains($haystack, mb_strtolower($concept->name))) {
                $ids[] = $concept->id;
            }
        }

        return $ids;
    }
}
