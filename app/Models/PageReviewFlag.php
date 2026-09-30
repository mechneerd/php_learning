<?php

namespace App\Models;

use App\Enums\PageFlagReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pdf_page_id
 * @property PageFlagReason $reason
 * @property string|null $note
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reason', 'note', 'resolved_at', 'resolved_by'])]
class PageReviewFlag extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => PageFlagReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PdfPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(PdfPage::class, 'pdf_page_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
