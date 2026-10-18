<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A figure/image extracted from the source book PDF and shown inside a
 * lesson. One row per figure reference (e.g. "16-1") with provenance.
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $figure_ref
 * @property string|null $caption
 * @property string $alt
 * @property string $path
 * @property string $disk
 * @property int|null $width
 * @property int|null $height
 * @property string $source
 * @property int|null $page_printed
 * @property int $page_pdf
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable([
    'lesson_id', 'figure_ref', 'caption', 'alt', 'path', 'disk', 'width', 'height',
    'source', 'page_printed', 'page_pdf',
])]
class LessonMedia extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'page_printed' => 'integer',
            'page_pdf' => 'integer',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Public URL relative to the web root (figures live in public/figures).
     */
    public function url(): string
    {
        return 'figures/'.$this->path;
    }
}
