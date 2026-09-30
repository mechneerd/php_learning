<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Placeholder used until Phase 6 wires a real provider. It fails loudly if
 * any code path accidentally calls the AI while reading content.
 */
final class NullAiClient implements AiClient
{
    public function complete(string $prompt, array $context = []): string
    {
        throw new RuntimeException('AI client is not configured yet (Phase 6).');
    }
}
