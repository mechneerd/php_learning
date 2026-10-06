<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI-compatible chat completions driver (provider-agnostic shape: any
 * endpoint that speaks /chat/completions works via ai.base_url).
 */
final class OpenAiClient implements AiClient
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $prompt, array $context = []): string
    {
        $key = (string) config('ai.api_key', '');

        if ($key === '') {
            throw new RuntimeException('AI_API_KEY is not set but ai.provider is "openai".');
        }

        $system = (string) ($context['system'] ?? 'You are a patient PHP tutor.');
        $model = (string) ($context['model'] ?? config('ai.model_fast'));
        $base = rtrim((string) (config('ai.base_url') ?: 'https://api.openai.com/v1'), '/');

        $response = Http::withToken($key)
            ->timeout((int) config('ai.timeout', 60))
            ->post($base.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => (int) config('ai.max_tokens', 4000),
                'temperature' => (float) ($context['temperature'] ?? config('ai.temperature', 0.2)),
            ])
            ->throw();

        return (string) $response->json('choices.0.message.content', '');
    }
}
