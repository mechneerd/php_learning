<?php

namespace App\Models;

use App\Enums\PdfDocumentStatus;
use Database\Factories\PdfDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $book_id
 * @property string $original_name
 * @property string $path
 * @property string $sha256
 * @property int $page_count
 * @property PdfDocumentStatus $status
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PdfPage> $pages
 */
#[Fillable(['original_name', 'path', 'sha256', 'page_count', 'status', 'error'])]
class PdfDocument extends Model
{
    /** @use HasFactory<PdfDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PdfDocumentStatus::class,
            'page_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return HasMany<PdfPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(PdfPage::class)->orderBy('page_pdf');
    }

    /** @return HasMany<ImportJob, $this> */
    public function importJobs(): HasMany
    {
        return $this->hasMany(ImportJob::class)->latest();
    }
}
