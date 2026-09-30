<?php

namespace App\Services\Ai;

/**
 * Contract for AI-backed features (Phase 6). Reading a lesson must never
 * resolve or call this service — see tests/Feature/Lessons/NoAiCallOnViewTest.
 */
interface AiClient
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $prompt, array $context = []): string;
}
