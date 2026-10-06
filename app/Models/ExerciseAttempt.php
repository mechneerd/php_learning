<?php

namespace App\Models;

use App\Enums\AttemptResult;
use Database\Factories\ExerciseAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    /** @use HasFactory<ExerciseAttemptFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'result' => AttemptResult::class,
        'hints_used' => 'integer',
        'duration_sec' => 'integer',
        'test_results' => 'array',
        'created_at' => 'datetime',
    ];

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
