<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ExerciseDifficulty;
use App\Enums\ProvenanceSource;
use Database\Factories\InterviewQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An interview-mode question (docs/08 §10): free-text attempt, then the
 * model answer with follow-ups; self-grading queues spaced revision.
 *
 * @property int $id
 * @property int|null $concept_id
 * @property string $topic
 * @property string $question
 * @property string $model_answer
 * @property list<string>|null $follow_ups
 * @property ExerciseDifficulty $difficulty
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
 * @property-read Concept|null $concept
 */
#[Fillable([
    'concept_id', 'topic', 'question', 'model_answer', 'follow_ups', 'difficulty', 'ord',
    'source', 'status', 'page_printed_from', 'page_printed_to', 'page_pdf_from', 'page_pdf_to',
    'ai_model', 'ai_generated_at', 'ai_prompt_version', 'reviewed_by', 'reviewed_at', 'is_outdated',
])]
class InterviewQuestion extends Model
{
    /** @use HasFactory<InterviewQuestionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'follow_ups' => 'array',
            'difficulty' => ExerciseDifficulty::class,
            'ord' => 'integer',
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
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /**
     * @param  Builder<InterviewQuestion>  $query
     * @return Builder<InterviewQuestion>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
