<?php

namespace App\Models;

use Database\Factories\PdfPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pdf_document_id
 * @property int $page_pdf
 * @property int|null $page_printed
 * @property string|null $text
 * @property int $word_count
 * @property float $extraction_quality
 * @property bool $needs_review
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PageReviewFlag> $flags
 */
#[Fillable([
    'pdf_document_id', 'page_pdf', 'page_printed', 'text', 'word_count',
    'extraction_quality', 'needs_review',
])]
class PdfPage extends Model
{
    /** @use HasFactory<PdfPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_pdf' => 'integer',
            'page_printed' => 'integer',
            'word_count' => 'integer',
            'extraction_quality' => 'float',
            'needs_review' => 'boolean',
        ];
    }

    /** @return BelongsTo<PdfDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'pdf_document_id');
    }

    /** @return HasMany<PageReviewFlag, $this> */
    public function flags(): HasMany
    {
        return $this->hasMany(PageReviewFlag::class, 'pdf_page_id');
    }
}
