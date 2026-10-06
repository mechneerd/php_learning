<?php

namespace App\Models;

use App\Enums\RunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exercise_id
 * @property string $attempt_id
 * @property string $code
 * @property RunStatus $status
 * @property string|null $stdout
 * @property string|null $stderr
 * @property int|null $exit_code
 * @property array<string, mixed>|null $metrics
 * @property int|null $duration_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Exercise $exercise
 */
#[Fillable([
    'user_id', 'exercise_id', 'attempt_id', 'code', 'status', 'stdout', 'stderr',
    'exit_code', 'metrics', 'duration_ms', 'error',
])]
class CodeRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'exit_code' => 'integer',
            'metrics' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
