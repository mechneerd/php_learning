<?php

namespace App\Models;

use App\Enums\MasteryLevel;
use Database\Factories\ConceptMasteryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One learner's mastery row for one concept (docs/06 module G).
 *
 * @property int $id
 * @property int $user_id
 * @property int $concept_id
 * @property MasteryLevel $level
 * @property array<string, int> $evidence evidence key => times earned
 * @property Carbon|null $mastered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Concept $concept
 */
#[Fillable(['user_id', 'concept_id', 'level', 'evidence', 'mastered_at'])]
class ConceptMastery extends Model
{
    /** @use HasFactory<ConceptMasteryFactory> */
    use HasFactory;

    protected $table = 'concept_mastery';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => MasteryLevel::class,
            'evidence' => 'array',
            'mastered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function hasEvidence(string $key): bool
    {
        return isset($this->evidence[$key]);
    }
}
