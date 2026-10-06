<?php

namespace App\Models;

use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A queued revision item (docs/06 module G). Derived rows are upserted by
 * ReviewScheduler; explicit rows may be snoozed or completed by the learner.
 *
 * @property int $id
 * @property int $user_id
 * @property ReviewItemType $item_type
 * @property int $item_id
 * @property int|null $concept_id
 * @property string $reason
 * @property Carbon $due_at
 * @property ReviewItemStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Concept|null $concept
 */
#[Fillable(['user_id', 'item_type', 'item_id', 'concept_id', 'reason', 'due_at', 'status'])]
class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => ReviewItemType::class,
            'status' => ReviewItemStatus::class,
            'due_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function snooze(int $days = 1): void
    {
        $this->forceFill([
            'status' => ReviewItemStatus::Snoozed,
            'due_at' => now()->addDays($days),
        ])->save();
    }

    public function complete(): void
    {
        $this->forceFill(['status' => ReviewItemStatus::Done])->save();
    }
}
