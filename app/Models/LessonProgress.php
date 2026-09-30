<?php

namespace App\Models;

use App\Enums\ProgressState;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-learner reading progress for a lesson (unique per user + lesson).
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property ProgressState $state
 * @property int $active_seconds
 * @property Carbon|null $opened_at
 * @property Carbon|null $last_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Lesson $lesson
 */
#[Fillable([
    'user_id', 'lesson_id', 'state', 'active_seconds', 'opened_at', 'last_at',
])]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ProgressState::class,
            'active_seconds' => 'integer',
            'opened_at' => 'datetime',
            'last_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Add focused reading time; never downgrades a reached state.
     */
    public function recordFocus(int $seconds): void
    {
        $this->forceFill([
            'active_seconds' => $this->active_seconds + max(0, $seconds),
            'last_at' => now(),
        ])->save();
    }

    public function upgradeTo(ProgressState $state): void
    {
        if ($state->rank() <= $this->state->rank()) {
            return;
        }

        $this->forceFill(['state' => $state])->save();
    }
}
