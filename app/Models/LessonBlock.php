<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\ProvenanceSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_id
 * @property int $ord
 * @property BlockType $type
 * @property array<string, mixed> $payload
 * @property ProvenanceSource $source
 * @property int|null $page_printed_from
 * @property int|null $page_printed_to
 * @property int|null $page_pdf_from
 * @property int|null $page_pdf_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable([
    'ord', 'type', 'payload', 'source', 'page_printed_from', 'page_printed_to',
    'page_pdf_from', 'page_pdf_to',
])]
class LessonBlock extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ord' => 'integer',
            'type' => BlockType::class,
            'payload' => 'array',
            'source' => ProvenanceSource::class,
            'page_printed_from' => 'integer',
            'page_printed_to' => 'integer',
            'page_pdf_from' => 'integer',
            'page_pdf_to' => 'integer',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Markdown/text content for block types that carry it.
     */
    public function text(): ?string
    {
        $payload = $this->payload;

        foreach (['markdown', 'text', 'content', 'title'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        return null;
    }
}
