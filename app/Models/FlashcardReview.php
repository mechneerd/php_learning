<?php

namespace App\Models;

use App\Enums\HintGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardReview extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'grade' => HintGrade::class,
        'interval_days' => 'integer',
        'ease' => 'float',
        'next_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Flashcard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class, 'card_id');
    }
}
