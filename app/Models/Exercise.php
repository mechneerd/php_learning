<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ExerciseDifficulty;
use App\Enums\ExerciseType;
use App\Enums\ProvenanceSource;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => ExerciseType::class,
        'difficulty' => ExerciseDifficulty::class,
        'source' => ProvenanceSource::class,
        'status' => ContentStatus::class,
        'expected_answer' => 'array',
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

    /** @return HasMany<ExerciseHint, $this> */
    public function hints(): HasMany
    {
        return $this->hasMany(ExerciseHint::class)->orderBy('level');
    }

    /** @return HasMany<ExerciseTest, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(ExerciseTest::class)->orderBy('ord');
    }

    /** @return HasMany<ExerciseAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    /**
     * @param  Builder<Exercise>  $query
     * @return Builder<Exercise>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
