<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseHint extends Model
{
    protected $guarded = [];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
