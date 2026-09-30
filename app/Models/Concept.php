<?php

namespace App\Models;

use App\Enums\ConceptGranularity;
use App\Enums\ContentSource;
use App\Enums\ContentStatus;
use Database\Factories\ConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $definition
 * @property string $skill_domain
 * @property ConceptGranularity $granularity
 * @property bool $is_core
 * @property ContentSource $source
 * @property ContentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, Concept> $prerequisites
 * @property-read Collection<int, Concept> $dependents
 */
#[Fillable(['slug', 'name', 'definition', 'skill_domain', 'granularity', 'is_core', 'source', 'status'])]
class Concept extends Model
{
    /** @use HasFactory<ConceptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granularity' => ConceptGranularity::class,
            'is_core' => 'boolean',
            'source' => ContentSource::class,
            'status' => ContentStatus::class,
        ];
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_concepts')->withPivot('role');
    }

    /**
     * What must be learned first: pivot rows where this concept is the target.
     *
     * @return BelongsToMany<Concept, $this>
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'concept_prerequisites', 'concept_id', 'prereq_concept_id')
            ->withPivot(['weight', 'source']);
    }

    /**
     * What uses this concept later: pivot rows where this concept is the source.
     *
     * @return BelongsToMany<Concept, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'concept_prerequisites', 'prereq_concept_id', 'concept_id')
            ->withPivot(['weight', 'source']);
    }
}
