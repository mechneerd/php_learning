<?php

namespace App\Models;

use App\Enums\NoteKind;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A learner note attached to any content row (docs/06 module G).
 *
 * @property int $id
 * @property int $user_id
 * @property string $noteable_type
 * @property int $noteable_id
 * @property NoteKind $kind
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model $noteable
 */
#[Fillable(['user_id', 'noteable_type', 'noteable_id', 'kind', 'body'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NoteKind::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }
}
