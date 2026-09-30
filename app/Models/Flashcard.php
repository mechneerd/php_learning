<?php

namespace App\Models;

use App\Enums\CardType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use Database\Factories\FlashcardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flashcard extends Model
{
    /** @use HasFactory<FlashcardFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'card_type' => CardType::class,
        'source' => ProvenanceSource::class,
        'status' => ContentStatus::class,
        'page_printed_from' => 'integer',
        'page_printed_to' => 'integer',
        'page_pdf_from' => 'integer',
        'page_pdf_to' => 'integer',
        'ai_generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'is_outdated' => 'boolean',
    ];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', ContentStatus::Published);
    }
}
