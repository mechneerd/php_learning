<?php

namespace App\Models;

use App\Enums\HintGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardReview extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'grade' => HintGrade::class,
        'interval_days' => 'integer',
        'ease' => 'float',
        'next_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class, 'card_id');
    }
}
