<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Enums\ProvenanceSource;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\LessonBlock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared persistence helpers for Pipeline A jobs: provenance bundles for
 * generated rows and mid-lesson block insertion (before the reference
 * blocks at the end of the lesson template).
 */
final class PipelineWriter
{
    private const REF_TYPES = [
        BlockType::ExerciseRef,
        BlockType::QuizRef,
        BlockType::CardRefs,
        BlockType::InterviewRef,
    ];

    public function __construct(private readonly BlockValidator $validator) {}

    /**
     * Provenance columns for any row generated from a lesson's pages.
     *
     * @return array<string, mixed>
     */
    public function provenance(Lesson $lesson, ProvenanceSource $source = ProvenanceSource::Ai): array
    {
        return [
            'source' => $source,
            'page_printed_from' => $lesson->page_printed_from,
            'page_printed_to' => $lesson->page_printed_to,
            'page_pdf_from' => $lesson->page_pdf_from,
            'page_pdf_to' => $lesson->page_pdf_to,
            'ai_model' => (string) config('ai.model_quality'),
            'ai_generated_at' => now(),
            'ai_prompt_version' => (string) config('ai.prompt_versions.lesson', 'v1'),
        ];
    }

    /**
     * Insert a generated block just before the trailing reference blocks
     * (so it still lands inside the open reading flow), or append it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function insertBeforeRefs(Lesson $lesson, BlockType $type, array $payload, ProvenanceSource $source): LessonBlock
    {
        $validated = $this->validator->validate($type, $payload);

        $anchor = LessonBlock::query()
            ->where('lesson_id', $lesson->id)
            ->whereIn('type', array_map(static fn (BlockType $t): string => $t->value, self::REF_TYPES))
            ->orderBy('ord')
            ->first();

        if ($anchor === null) {
            $ord = (int) LessonBlock::query()->where('lesson_id', $lesson->id)->max('ord') + 1;
        } else {
            LessonBlock::query()
                ->where('lesson_id', $lesson->id)
                ->where('ord', '>=', $anchor->ord)
                ->orderByDesc('ord')
                ->get()
                ->each(static function (LessonBlock $block): void {
                    LessonBlock::query()
                        ->where('id', $block->id)
                        ->update(['ord' => $block->ord + 1]);
                });

            $ord = $anchor->ord;
        }

        return LessonBlock::query()->create([
            'lesson_id' => $lesson->id,
            'ord' => $ord,
            'type' => $type,
            'payload' => $validated,
            'source' => $source,
            'page_printed_from' => $lesson->page_printed_from,
            'page_printed_to' => $lesson->page_printed_to,
            'page_pdf_from' => $lesson->page_pdf_from,
            'page_pdf_to' => $lesson->page_pdf_to,
        ]);
    }

    /**
     * Replace an existing block of this type (modern panels on re-run) or
     * insert it fresh.
     *
     * @param  array<string, mixed>  $payload
     */
    public function upsertPanel(Lesson $lesson, array $payload): LessonBlock
    {
        $validated = $this->validator->validate(BlockType::ModernPanel, $payload);

        $existing = LessonBlock::query()
            ->where('lesson_id', $lesson->id)
            ->where('type', BlockType::ModernPanel->value)
            ->orderBy('ord')
            ->first();

        if ($existing !== null) {
            $existing->update(['payload' => $validated]);

            return $existing;
        }

        return $this->insertBeforeRefs($lesson, BlockType::ModernPanel, $validated, ProvenanceSource::Ai);
    }

    /**
     * True when a lesson's prose mentions the concept (used to link the
     * graph to generated lessons without over-linking).
     *
     * @param  Collection<int, Concept>  $concepts
     * @return list<int> concept ids to attach
     */
    public function matchingConceptIds(string $text, $concepts): array
    {
        $haystack = ' '.mb_strtolower($text).' ';

        $ids = [];

        foreach ($concepts as $concept) {
            $needle = ' '.mb_strtolower($concept->slug).' ';

            if (str_contains($haystack, trim($needle)) || str_contains($haystack, mb_strtolower($concept->name))) {
                $ids[] = $concept->id;
            }
        }

        return $ids;
    }
}
