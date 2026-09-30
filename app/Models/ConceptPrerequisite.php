<?php

namespace App\Models;

use App\Enums\ContentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Edge of the prerequisite graph: `$concept` can only be mastered after
 * `$prereq_concept` (weight >= 1 gates; weight 0 is a soft "used later" edge).
 *
 * @property int $concept_id
 * @property int $prereq_concept_id
 * @property int $weight
 * @property ContentSource $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Concept|null $concept
 * @property-read Concept|null $prereqConcept
 */
#[Fillable(['concept_id', 'prereq_concept_id', 'weight', 'source'])]
class ConceptPrerequisite extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'source' => ContentSource::class,
        ];
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return BelongsTo<Concept, $this> */
    public function prereqConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'prereq_concept_id');
    }
}
