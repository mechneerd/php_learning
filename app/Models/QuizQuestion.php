<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Enums\QuizQuestionType;
use Database\Factories\QuizQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model
{
    /** @use HasFactory<QuizQuestionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => QuizQuestionType::class,
        'source' => ProvenanceSource::class,
        'status' => ContentStatus::class,
        'page_printed_from' => 'integer',
        'page_printed_to' => 'integer',
        'page_pdf_from' => 'integer',
        'page_pdf_to' => 'integer',
        'ai_generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'is_outdated' => 'boolean',
    ];

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return HasMany<QuizOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id')->orderBy('ord');
    }

    /**
     * @param  Builder<QuizQuestion>  $query
     * @return Builder<QuizQuestion>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
