<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI provider
    |--------------------------------------------------------------------------
    |
    | "stub" returns schema-valid canned responses so the whole application
    | works with no API key (this is also what tests use).
    |
    */

    'provider' => env('AI_PROVIDER', 'stub'),

    'api_key' => env('AI_API_KEY'),

    'base_url' => env('AI_BASE_URL'),

    'model_fast' => env('AI_MODEL_FAST', 'gpt-4o-mini'),

    'model_quality' => env('AI_MODEL_QUALITY', 'gpt-4o'),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 4000),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    /*
    |--------------------------------------------------------------------------
    | Budgets and rate limits
    |--------------------------------------------------------------------------
    */

    'daily_token_budget' => (int) env('AI_DAILY_TOKEN_BUDGET', 500000),

    /*
    |---------------------------------------------------------------------
    | Shared budget for Pipeline A (content generation jobs). Exceeding
    | it fails queued generations with reason "budget" (docs/10).
    */

    'daily_generation_budget' => (int) env('AI_DAILY_GENERATION_BUDGET', 2000000),

    'tutor_messages_per_10_minutes' => (int) env('AI_TUTOR_RATE', 20),

    /*
    |--------------------------------------------------------------------------
    | Prompt versions
    |--------------------------------------------------------------------------
    |
    | Bumping a version invalidates the idempotency hash in ai_generations
    | so content can be regenerated with the improved prompt.
    |
    */

    'prompt_versions' => [
        'lesson' => 'v1',
        'exercise' => 'v1',
        'quiz' => 'v1',
        'cards' => 'v1',
        'diagram' => 'v1',
        'concepts' => 'v1',
        'prerequisites' => 'v1',
        'outdated' => 'v1',
        'tutor' => 'v1',
        'recall' => 'v1',
    ],
];
