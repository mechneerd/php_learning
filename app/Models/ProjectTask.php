<?php

namespace App\Models;

use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One step of a project: a task brief, the concept slugs it exercises and
 * an optional solution hint. Checks are the learner's per-task progress.
 *
 * @property int $id
 * @property int $project_id
 * @property int $ord
 * @property string $brief
 * @property list<string>|null $concept_ids
 * @property string|null $solution_hint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Collection<int, ProjectTaskCheck> $checks
 */
#[Fillable(['project_id', 'ord', 'brief', 'concept_ids', 'solution_hint'])]
class ProjectTask extends Model
{
    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ord' => 'integer',
            'concept_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProjectTaskCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ProjectTaskCheck::class);
    }
}
