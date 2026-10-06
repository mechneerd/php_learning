<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\DiagramKind;
use App\Enums\DiagramSource;
use Database\Factories\DiagramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mermaid diagram attached to (or referenced by) a lesson.
 *
 * @property int $id
 * @property int|null $lesson_id
 * @property DiagramKind $kind
 * @property string $title
 * @property string $mermaid_source
 * @property DiagramSource $source
 * @property string|null $figure_ref
 * @property int|null $page_pdf
 * @property ContentStatus $status
 * @property string|null $ai_model
 * @property Carbon|null $ai_generated_at
 * @property string|null $ai_prompt_version
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property bool $is_outdated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson|null $lesson
 */
#[Fillable([
    'lesson_id', 'kind', 'title', 'mermaid_source', 'source', 'figure_ref',
    'page_pdf', 'status', 'ai_model', 'ai_generated_at', 'ai_prompt_version',
    'reviewed_by', 'reviewed_at', 'is_outdated',
])]
class Diagram extends Model
{
    /** @use HasFactory<DiagramFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DiagramKind::class,
            'source' => DiagramSource::class,
            'status' => ContentStatus::class,
            'page_pdf' => 'integer',
            'ai_generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_outdated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isFromBook(): bool
    {
        return $this->source === DiagramSource::BookFigure;
    }

    public function sourceLabel(): string
    {
        return $this->source->label();
    }

    public function citation(): ?string
    {
        $parts = [];

        if ($this->figure_ref !== null) {
            $parts[] = 'Fig. '.$this->figure_ref;
        }

        if ($this->page_pdf !== null) {
            $parts[] = 'PDF '.$this->page_pdf;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
