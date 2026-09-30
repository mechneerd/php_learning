<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Models\Chapter;
use App\Models\CodeExample;
use App\Models\Diagram;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Section;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Assembles everything the lesson screen needs in one query set:
 * ordered validated blocks plus the code examples and diagrams they reference.
 * The result is cached per lesson version (lesson.updated_at + block changes).
 */
final class LessonBuilder
{
    public function __construct(private readonly BlockValidator $validator) {}

    /**
     * @return array{
     *     lesson: array<string, mixed>,
     *     blocks: list<array<string, mixed>>,
     *     code_examples: array<int, array<string, mixed>>,
     *     diagrams: array<int, array<string, mixed>>,
     * }
     */
    public function build(Lesson $lesson): array
    {
        $lesson->loadMissing(['stage', 'chapter']);

        [$count, $latest] = $this->blockVersion($lesson->id);
        $key = 'lessons:render:'.implode(':', [$lesson->id, $lesson->updated_at?->getTimestamp(), $count, $latest]);

        /** @var array{lesson: array<string, mixed>, blocks: list<array<string, mixed>>, code_examples: array<int, array<string, mixed>>, diagrams: array<int, array<string, mixed>>} */
        return Cache::remember($key, now()->addHour(), fn (): array => $this->assemble($lesson));
    }

    /**
     * Published chapter/section/lesson tree for the left navigation pane.
     *
     * @return list<array<string, mixed>>
     */
    public function navTree(): array
    {
        $stats = Lesson::published()
            ->selectRaw('count(*) as c, max(updated_at) as m')
            ->toBase()
            ->first();

        $key = 'lessons:tree:'.implode(':', [$stats->c ?? 0, $stats->m ?? 'none']);

        /** @var list<array<string, mixed>> */
        return Cache::remember($key, now()->addHour(), fn (): array => $this->assembleTree());
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function blockVersion(int $lessonId): array
    {
        $row = LessonBlock::query()
            ->where('lesson_id', $lessonId)
            ->selectRaw('count(*) as c, max(updated_at) as m')
            ->toBase()
            ->first();

        return [(int) ($row->c ?? 0), (string) ($row->m ?? 'none')];
    }

    /**
     * @return array{
     *     lesson: array<string, mixed>,
     *     blocks: list<array<string, mixed>>,
     *     code_examples: array<int, array<string, mixed>>,
     *     diagrams: array<int, array<string, mixed>>,
     * }
     */
    private function assemble(Lesson $lesson): array
    {
        /** @var Collection<int, LessonBlock> $blocks */
        $blocks = $lesson->blocks()->get();

        $exampleIds = [];
        $diagramIds = [];

        foreach ($blocks as $block) {
            if ($block->type === BlockType::CodeExample) {
                $id = $block->payload['code_example_id'] ?? null;

                if (is_int($id)) {
                    $exampleIds[] = $id;
                }
            }

            if ($block->type === BlockType::Diagram) {
                $id = $block->payload['diagram_id'] ?? null;

                if (is_int($id)) {
                    $diagramIds[] = $id;
                }
            }
        }

        $examples = $exampleIds === [] ? [] : CodeExample::whereIn('id', $exampleIds)->get()->mapWithKeys(
            static fn (CodeExample $example): array => [$example->id => $example->toArray()],
        )->all();

        $diagrams = $diagramIds === [] ? [] : Diagram::whereIn('id', $diagramIds)->get()->mapWithKeys(
            static fn (Diagram $diagram): array => [$diagram->id => $diagram->toArray()],
        )->all();

        $chapterNumber = $lesson->chapter?->number;
        $rows = [];

        foreach ($blocks as $block) {
            $rows[] = $this->blockArray($block, $chapterNumber);
        }

        return [
            'lesson' => array_merge($lesson->toArray(), [
                'citation' => $lesson->citation(),
                'source_label' => $lesson->sourceLabel(),
            ]),
            'blocks' => $rows,
            'code_examples' => $examples,
            'diagrams' => $diagrams,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockArray(LessonBlock $block, ?int $chapterNumber): array
    {
        $payload = $block->payload;

        try {
            $payload = $this->validator->validate($block->type, $payload);
        } catch (InvalidBlockPayload) {
            // Stored content must still render; the partial handles missing keys.
        }

        return [
            'id' => $block->id,
            'ord' => $block->ord,
            'type' => $block->type->value,
            'type_label' => $block->type->label(),
            'source' => $block->source->value,
            'collapsed' => $block->type->isCollapsedByDefault(),
            'citation' => $this->blockCitation($block, $chapterNumber),
            'payload' => $payload,
            'text' => $block->text(),
        ];
    }

    private function blockCitation(LessonBlock $block, ?int $chapterNumber): ?string
    {
        $parts = [];

        if ($chapterNumber !== null) {
            $parts[] = 'Ch. '.$chapterNumber;
        }

        if ($block->page_printed_from !== null) {
            $to = $block->page_printed_to;
            $parts[] = 'pp. '.$block->page_printed_from.($to !== null && $to !== $block->page_printed_from ? '–'.$to : '');
        }

        if ($block->page_pdf_from !== null) {
            $to = $block->page_pdf_to;
            $parts[] = 'PDF '.$block->page_pdf_from.($to !== null && $to !== $block->page_pdf_from ? '–'.$to : '');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assembleTree(): array
    {
        $chapters = Chapter::query()->with('sections')->orderBy('number')->get();

        $lessons = Lesson::published()
            ->whereNotNull('chapter_id')
            ->orderBy('ord')
            ->get(['id', 'title', 'slug', 'chapter_id', 'section_id', 'status']);

        return array_values($chapters->map(function (Chapter $chapter) use ($lessons): array {
            $chapterLessons = $lessons->where('chapter_id', $chapter->id);

            return [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
                'slug' => $chapter->slug,
                'page_printed_from' => $chapter->page_printed_from,
                'page_printed_to' => $chapter->page_printed_to,
                'sections' => $chapter->sections->map(function (Section $section) use ($chapterLessons): array {
                    $sectionLessons = $chapterLessons->where('section_id', $section->id);

                    return [
                        'id' => $section->id,
                        'title' => $section->title,
                        'level' => $section->level,
                        'ord' => $section->ord,
                        'page_printed_from' => $section->page_printed_from,
                        'page_printed_to' => $section->page_printed_to,
                        'lessons' => $sectionLessons->map(fn (Lesson $lesson): array => [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'slug' => $lesson->slug,
                        ])->values()->all(),
                    ];
                })->values()->all(),
                'lessons' => $chapterLessons
                    ->whereNull('section_id')
                    ->map(fn (Lesson $lesson): array => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'slug' => $lesson->slug,
                    ])
                    ->values()
                    ->all(),
            ];
        })->all());
    }
}
