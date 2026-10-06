<?php

namespace App\Livewire\Admin;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Jobs\GenerateLessonJob;
use App\Models\Lesson;
use App\Services\Ai\Generators\LessonGenerator;
use App\Services\Content\BlockValidator;
use App\Services\Content\LessonBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * /admin/lessons - block-level JSON editor for lessons: any save flips the
 * row to in_review (human gate, never auto-publish; docs/08 screen table).
 */
#[Title('Lesson Editor')]
class LessonEditor extends Component
{
    public ?int $lessonId = null;

    public string $title = '';

    public string $summary = '';

    public int $estMinutes = 0;

    public string $status = 'draft';

    public ?int $editIndex = null;

    public string $editJson = '';

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(int|string|null $lessonId = null): void
    {
        $this->lessonId = $lessonId !== null ? (int) $lessonId : null;
        $this->load();
    }

    public function select(int $id): void
    {
        $this->lessonId = $id;
        $this->notice = null;
        $this->error = null;
        $this->load();
    }

    public function backToList(): void
    {
        $this->lessonId = null;
        $this->load();
    }

    public function startEdit(int $index): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        $block = $lesson->blocks->values()[$index] ?? null;

        if ($block === null) {
            return;
        }

        $this->editIndex = $index;
        $this->editJson = json_encode($block->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
        $this->error = null;
    }

    public function cancelEdit(): void
    {
        $this->editIndex = null;
        $this->editJson = '';
        $this->error = null;
    }

    public function saveBlock(BlockValidator $validator): void
    {
        $lesson = $this->lesson();
        $block = $lesson?->blocks->values()[$this->editIndex ?? -1] ?? null;

        if ($lesson === null || $block === null) {
            return;
        }

        $decoded = json_decode($this->editJson, true);

        if (! is_array($decoded)) {
            $this->error = 'Payload must be a JSON object.';

            return;
        }

        try {
            $validator->validate($block->type, $decoded);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $lesson->forceFill(['status' => ContentStatus::InReview->value, 'reviewed_by' => null, 'reviewed_at' => null])->save();
        $block->forceFill(['payload' => $decoded])->save();

        $this->notice = 'Block saved - lesson moved to in review.';
        $this->cancelEdit();
    }

    public function move(int $index, int $delta): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        $blocks = $lesson->blocks->values();
        $target = $index + $delta;

        if ($target < 0 || $target >= $blocks->count()) {
            return;
        }

        [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];

        $count = $blocks->count();
        $base = $blocks->max('ord') + 1;

        foreach ($blocks as $offset => $block) {
            $block->forceFill(['ord' => $base + $offset])->save();
        }

        foreach ($blocks as $ord => $block) {
            $block->forceFill(['ord' => $ord])->save();
        }

        $lesson->forceFill(['status' => ContentStatus::InReview->value, 'reviewed_by' => null, 'reviewed_at' => null])->save();
        $this->notice = 'Block moved - lesson moved to in review.';
    }

    public function deleteBlock(int $index): void
    {
        $lesson = $this->lesson();
        $block = $lesson?->blocks->values()[$index] ?? null;

        if ($lesson === null || $block === null) {
            return;
        }

        $block->delete();
        $lesson->forceFill(['status' => ContentStatus::InReview->value, 'reviewed_by' => null, 'reviewed_at' => null])->save();
        $this->notice = 'Block deleted - lesson moved to in review.';
        $this->cancelEdit();
    }

    public function addBlock(string $type, BlockValidator $validator): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        if (! BlockType::tryFrom($type)) {
            $this->error = "Unknown block type '{$type}'.";

            return;
        }

        $blockType = BlockType::from($type);
        $payload = $this->defaultPayload($blockType);

        try {
            $validated = $validator->validate($blockType, $payload);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $lastOrd = (int) $lesson->blocks()->max('ord');
        $lesson->blocks()->create([
            'type' => $blockType,
            'payload' => $validated,
            'source' => $blockType === BlockType::BookQuote ? ProvenanceSource::Book : ProvenanceSource::Ai,
            'ord' => $lastOrd + 1,
        ]);
        $lesson->forceFill(['status' => ContentStatus::InReview, 'reviewed_by' => null, 'reviewed_at' => null])->save();
        $this->notice = 'Block added - lesson moved to in review.';
    }

    public function saveLessonMeta(): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        $lesson->forceFill([
            'title' => $this->title,
            'summary' => $this->summary,
            'est_minutes' => max(1, $this->estMinutes),
            'status' => $lesson->status === ContentStatus::Published ? ContentStatus::Published : ContentStatus::InReview,
        ])->save();

        $this->notice = 'Lesson details saved.';
    }

    public function regenerate(): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        if ($lesson->section_id === null) {
            $this->error = 'No book section attached - cannot regenerate.';

            return;
        }

        $job = new GenerateLessonJob($lesson->section_id, $lesson->stage_id, force: true);
        $job->handle(app(LessonGenerator::class), app(BlockValidator::class));

        $this->notice = 'Regenerated from the book (inline).';
        $this->load();
    }

    public function render(): View
    {
        $lesson = $this->lesson();

        return view('livewire.admin.lesson-editor', [
            'lessons' => Lesson::query()->orderByDesc('id')->limit(100)->get(),
            'preview' => $lesson !== null ? app(LessonBuilder::class)->build($lesson) : null,
            'blocks' => $lesson !== null ? $lesson->blocks->values()->map(fn ($b): array => [
                'ord' => (int) $b->ord,
                'type' => $b->type->value,
                'payload_json' => (string) (json_encode($b->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: ''),
            ])->all() : [],
        ]);
    }

    private function lesson(): ?Lesson
    {
        return $this->lessonId !== null ? Lesson::query()->find($this->lessonId) : null;
    }

    private function load(): void
    {
        $lesson = $this->lesson();

        if ($lesson === null) {
            return;
        }

        $this->title = (string) $lesson->title;
        $this->summary = (string) $lesson->summary;
        $this->estMinutes = (int) $lesson->est_minutes;
        $this->status = $lesson->status->value;
        $this->editIndex = null;
        $this->editJson = '';
        $this->error = null;
    }

    /**
     * Default payloads for the add-block menu (spec per BlockValidator).
     *
     * @return array<string, mixed>
     */
    private function defaultPayload(BlockType $type): array
    {
        return match ($type) {
            BlockType::Heading => ['text' => 'New heading', 'level' => 2],
            BlockType::Paragraph => ['markdown' => 'Write the paragraph here.'],
            BlockType::Bullets => ['items' => ['First point', 'Second point']],
            BlockType::Callout => ['text' => 'Key idea.', 'variant' => 'tip'],
            BlockType::Code => ['lang' => 'php', 'code' => "<?php\n\necho 'ok';\n"],
            BlockType::Output => ['text' => 'ok'],
            BlockType::Table => ['headers' => ['Column'], 'rows' => [['Value']]],
            BlockType::BookQuote => ['text' => 'Quoted from the book.', 'attribution' => 'Matt Zandstra'],
            BlockType::ModernPanel => ['book' => 'What the book says.', 'modern' => 'What changed.', 'why' => 'Why it changed.'],
            BlockType::PrereqList => ['items' => ['Variables and types']],
            BlockType::ExerciseRef => ['labels' => ['Practice this'], 'ids' => []],
            BlockType::QuizRef => ['labels' => ['Quick check'], 'ids' => []],
            BlockType::CardRefs => ['labels' => ['Recall term']],
            BlockType::InterviewRef => ['labels' => ['Interview question']],
            BlockType::Image => ['figure_ref' => 'figure-1', 'caption' => 'Caption'],
            BlockType::Tabs => ['tabs' => [['label' => 'Tab one', 'content' => 'Content here']]],
            default => ['diagram_id' => 1],
        };
    }
}
