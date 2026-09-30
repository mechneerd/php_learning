<?php

namespace App\Models;

use App\Concerns\HasProvenance;
use App\Enums\CodeTier;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use Database\Factories\CodeExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A runnable code sample attached to a lesson (tiered by difficulty).
 *
 * @property int $id
 * @property int $lesson_id
 * @property int|null $concept_id
 * @property string|null $listing_ref
 * @property CodeTier $tier
 * @property string $title
 * @property string $code
 * @property string|null $expected_output
 * @property string|null $explanation
 * @property string|null $syntax_notes
 * @property string|null $common_mistake
 * @property string|null $external_ref
 * @property int $ord
 * @property ProvenanceSource $source
 * @property ContentStatus $status
 * @property int|null $page_printed_from
 * @property int|null $page_printed_to
 * @property int|null $page_pdf_from
 * @property int|null $page_pdf_to
 * @property string|null $ai_model
 * @property Carbon|null $ai_generated_at
 * @property string|null $ai_prompt_version
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property bool $is_outdated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable([
    'lesson_id', 'concept_id', 'listing_ref', 'tier', 'title', 'code',
    'expected_output', 'explanation', 'syntax_notes', 'common_mistake',
    'external_ref', 'ord', 'source', 'status', 'page_printed_from',
    'page_printed_to', 'page_pdf_from', 'page_pdf_to', 'ai_model',
    'ai_generated_at', 'ai_prompt_version', 'reviewed_by', 'reviewed_at',
    'is_outdated',
])]
class CodeExample extends Model
{
    /** @use HasFactory<CodeExampleFactory> */
    use HasFactory, HasProvenance;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge([
            'tier' => CodeTier::class,
            'status' => ContentStatus::class,
            'ord' => 'integer',
            'page_printed_from' => 'integer',
            'page_printed_to' => 'integer',
            'page_pdf_from' => 'integer',
            'page_pdf_to' => 'integer',
        ], $this->provenanceCasts());
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function citation(): ?string
    {
        $parts = [];

        if ($this->listing_ref !== null) {
            $parts[] = 'Listing '.$this->listing_ref;
        }

        if ($this->page_printed_from !== null) {
            $parts[] = $this->pageRange('page_printed_from', 'page_printed_to', 'pp. ');
        }

        if ($this->page_pdf_from !== null) {
            $parts[] = $this->pageRange('page_pdf_from', 'page_pdf_to', 'PDF ');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
