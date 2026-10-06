<?php

namespace App\Models;

use App\Enums\MasteryLevel;
use Database\Factories\SkillProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aggregated level for one skill domain (docs/06 module G). Written by
 * SkillAggregator only - never by UI code.
 *
 * @property int $id
 * @property int $user_id
 * @property string $skill_domain
 * @property MasteryLevel $level
 * @property int $mastered_count
 * @property int $comfortable_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'skill_domain', 'level', 'mastered_count', 'comfortable_count'])]
class SkillProgress extends Model
{
    /** @use HasFactory<SkillProgressFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => MasteryLevel::class,
            'mastered_count' => 'integer',
            'comfortable_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
