<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ErrorCategory;
use App\Enums\ProvenanceSource;
use Database\Factories\ErrorPatternFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A repeatable error pattern (docs/04, FR-30): the six-step learner flow is
 * what happened → why → identify → fix → prevent → practice.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ErrorCategory $category
 * @property string $symptom
 * @property string $cause
 * @property list<string> $identify_steps
 * @property list<string> $fix_steps
 * @property list<string> $prevent_steps
 * @property string|null $practice_ref
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
 */
#[Fillable([
    'slug', 'name', 'category', 'symptom', 'cause', 'identify_steps', 'fix_steps', 'prevent_steps',
    'practice_ref', 'source', 'status', 'page_printed_from', 'page_printed_to', 'page_pdf_from',
    'page_pdf_to', 'ai_model', 'ai_generated_at', 'ai_prompt_version', 'reviewed_by', 'reviewed_at',
    'is_outdated',
])]
class ErrorPattern extends Model
{
    /** @use HasFactory<ErrorPatternFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ErrorCategory::class,
            'identify_steps' => 'array',
            'fix_steps' => 'array',
            'prevent_steps' => 'array',
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

    /**
     * @param  Builder<ErrorPattern>  $query
     * @return Builder<ErrorPattern>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
