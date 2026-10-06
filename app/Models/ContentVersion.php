<?php

namespace App\Models;

use App\Enums\AiActor;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot of an entity payload each time AI or an admin changes it
 * (version history for the admin review screens).
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property array<string, mixed> $payload
 * @property AiActor $actor
 * @property string|null $diff_summary
 */
class ContentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'actor' => AiActor::class,
            'entity_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
