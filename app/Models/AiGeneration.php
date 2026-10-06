<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for content-generation runs (Pipeline A, Phase 7+).
 *
 * @property int $id
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $stage
 * @property string $prompt_version
 * @property string $model
 * @property GenerationStatus $status
 * @property int $tokens_in
 * @property int $tokens_out
 * @property string|float $cost
 * @property string|null $error
 * @property string $input_hash
 */
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'entity_id' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
