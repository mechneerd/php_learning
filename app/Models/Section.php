<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chapter_id
 * @property int|null $parent_id
 * @property string|null $number
 * @property string $title
 * @property string $slug
 * @property int $level
 * @property int|null $page_printed_from
 * @property int|null $page_printed_to
 * @property int|null $page_pdf_from
 * @property int|null $page_pdf_to
 * @property int $ord
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Section|null $parent
 * @property-read Collection<int, Section> $children
 * @property-read Collection<int, Lesson> $lessons
 */
#[Fillable([
    'chapter_id', 'parent_id', 'number', 'title', 'slug', 'level',
    'page_printed_from', 'page_printed_to', 'page_pdf_from', 'page_pdf_to', 'ord',
])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'page_printed_from' => 'integer',
            'page_printed_to' => 'integer',
            'page_pdf_from' => 'integer',
            'page_pdf_to' => 'integer',
            'ord' => 'integer',
        ];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'parent_id');
    }

    /** @return HasMany<Section, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Section::class, 'parent_id')->orderBy('ord');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('ord');
    }

    /**
     * Ancestor chain from the chapter root down to this section.
     *
     * @return array<int, Section>
     */
    public function trail(): array
    {
        $trail = [$this];
        $seen = [$this->id];
        $current = $this->parent;

        while ($current !== null && ! in_array($current->id, $seen, true)) {
            array_unshift($trail, $current);
            $seen[] = $current->id;
            $current = $current->parent;
        }

        return $trail;
    }
}
