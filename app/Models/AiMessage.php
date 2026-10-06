<?php

namespace App\Models;

use App\Enums\AiRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property AiRole $role
 * @property string $content
 * @property int $tokens_in
 * @property int $tokens_out
 * @property array<string, mixed>|null $meta
 * @property-read AiConversation $conversation
 */
class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AiRole::class,
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
