<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\MasteryLevel;
use App\Enums\ProjectLevel;
use App\Enums\ProvenanceSource;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A guided project (docs/08 §11): brief, requirements checklist, ordered
 * tasks and a gated solution. Unlock gating: every concept named by the
 * project's tasks must be at least `comfortable` for the learner.
 *
 * @property int $id
 * @property int|null $stage_id
 * @property ProjectLevel $level
 * @property string $slug
 * @property string $title
 * @property string $brief
 * @property list<string> $requirements
 * @property string|null $solution_ref
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
 * @property-read Stage|null $stage
 * @property-read Collection<int, ProjectTask> $tasks
 */
#[Fillable([
    'stage_id', 'level', 'slug', 'title', 'brief', 'requirements', 'solution_ref', 'ord',
    'source', 'status', 'page_printed_from', 'page_printed_to', 'page_pdf_from', 'page_pdf_to',
    'ai_model', 'ai_generated_at', 'ai_prompt_version', 'reviewed_by', 'reviewed_at', 'is_outdated',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ProjectLevel::class,
            'source' => ProvenanceSource::class,
            'status' => ContentStatus::class,
            'requirements' => 'array',
            'ord' => 'integer',
            'page_printed_from' => 'integer',
            'page_printed_to' => 'integer',
            'page_pdf_from' => 'integer',
            'page_pdf_to' => 'integer',
            'ai_generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_outdated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return HasMany<ProjectTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('ord');
    }

    /**
     * Concept slugs required across all tasks (unique, ordered).
     *
     * @return list<string>
     */
    public function requiredConceptSlugs(): array
    {
        $slugs = [];

        foreach ($this->tasks as $task) {
            foreach ($task->concept_ids ?? [] as $slug) {
                $slugs[] = (string) $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * True when every known required concept is at least `comfortable`.
     * Slugs with no matching concept row are ignored (they cannot be evaluated).
     */
    public function isUnlockedFor(User $user): bool
    {
        $slugs = $this->requiredConceptSlugs();

        if ($slugs === []) {
            return true;
        }

        $conceptIds = Concept::query()
            ->whereIn('slug', $slugs)
            ->pluck('id');

        if ($conceptIds->isEmpty()) {
            return true;
        }

        $reached = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->whereIn('concept_id', $conceptIds)
            ->get(['concept_id', 'level'])
            ->filter(fn (ConceptMastery $row): bool => $row->level->atLeast(MasteryLevel::Comfortable));

        return $reached->count() === $conceptIds->count();
    }

    /**
     * @return array{checked: int, total: int}
     */
    public function progressFor(User $user): array
    {
        $taskIds = $this->tasks->pluck('id');

        $checked = ProjectTaskCheck::query()
            ->where('user_id', $user->id)
            ->whereIn('project_task_id', $taskIds)
            ->count();

        return ['checked' => $checked, 'total' => $taskIds->count()];
    }

    /**
     * The solution unlocks after the learner's first checked task (one attempt).
     */
    public function hasAttemptFor(User $user): bool
    {
        return ProjectTaskCheck::query()
            ->where('user_id', $user->id)
            ->whereIn('project_task_id', $this->tasks->pluck('id'))
            ->exists();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
