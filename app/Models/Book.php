<?php

namespace App\Models;

use App\Enums\BookStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $author
 * @property string|null $edition
 * @property string|null $isbn
 * @property string|null $publisher
 * @property int|null $published_year
 * @property int|null $source_pdf_document_id
 * @property BookStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Chapter> $chapters
 * @property-read Collection<int, PdfDocument> $pdfDocuments
 */
#[Fillable([
    'title', 'subtitle', 'author', 'edition', 'isbn', 'publisher',
    'published_year', 'status',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'published_year' => 'integer',
        ];
    }

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('number');
    }

    /** @return HasMany<PdfDocument, $this> */
    public function pdfDocuments(): HasMany
    {
        return $this->hasMany(PdfDocument::class);
    }

    /** @return BelongsTo<PdfDocument, $this> */
    public function sourcePdfDocument(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'source_pdf_document_id');
    }

    /** @return HasOne<PdfDocument, $this> */
    public function activePdfDocument(): HasOne
    {
        return $this->hasOne(PdfDocument::class)->latestOfMany();
    }
}
