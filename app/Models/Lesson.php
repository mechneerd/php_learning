<?php

namespace App\Models;

use App\Concerns\HasProvenance;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stage_id
 * @property int|null $chapter_id
 * @property int|null $section_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property ContentStatus $status
 * @property int $est_minutes
 * @property int $ord
 * @property ProvenanceSource $source
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
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Chapter|null $chapter
 * @property-read Stage|null $stage
 * @property-read Collection<int, LessonBlock> $blocks
 * @property-read Collection<int, Concept> $concepts
 */
#[Fillable([
    'stage_id', 'chapter_id', 'section_id', 'title', 'slug', 'summary', 'status',
    'est_minutes', 'ord', 'source', 'page_printed_from', 'page_printed_to',
    'page_pdf_from', 'page_pdf_to', 'ai_model', 'ai_generated_at',
    'ai_prompt_version', 'reviewed_by', 'reviewed_at', 'is_outdated',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, HasProvenance, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge([
            'status' => ContentStatus::class,
            'est_minutes' => 'integer',
        ], $this->provenanceCasts());
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<LessonBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->orderBy('ord');
    }

    /** @return BelongsToMany<Concept, $this> */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(Concept::class, 'lesson_concepts')->withPivot('role');
    }

    /** @return HasMany<Exercise, $this> */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('ord');
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('ord');
    }

    /** @return HasMany<Flashcard, $this> */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class)->orderBy('ord');
    }

    /**
     * @param  Builder<Lesson>  $query
     * @return Builder<Lesson>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Lesson>  $query
     * @return Builder<Lesson>
     */
    public function scopeForStage(Builder $query, Stage|int $stage): Builder
    {
        return $query->where('stage_id', $stage instanceof Stage ? $stage->id : $stage);
    }

    public function citation(): ?string
    {
        $parts = [];

        if ($this->chapter_id !== null && $this->chapter !== null) {
            $parts[] = 'Ch. '.$this->chapter->number;
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
