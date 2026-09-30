<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseTest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'ord' => 'integer',
        'weight' => 'integer',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
