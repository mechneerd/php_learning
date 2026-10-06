<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox runner (docs/12 §2, Phase 8)
    |--------------------------------------------------------------------------
    |
    | Learner code only ever executes inside the standalone runner service
    | in runner/. The app signs each request with RUNNER_SECRET; the runner
    | owns the authoritative limits (its own environment variables), while
    | these mirror them for pre-checks and friendly messages.
    |
    */

    'url' => env('RUNNER_URL', 'http://127.0.0.1:8090'),

    'secret' => env('RUNNER_SECRET'),

    'signature_ttl' => (int) env('RUNNER_SIGNATURE_TTL', 60),

    'timeout' => (float) env('RUNNER_HTTP_TIMEOUT', 6),

    'limits' => [
        'wall_seconds' => (float) env('RUNNER_WALL_SECONDS', 3),
        'kill_seconds' => (float) env('RUNNER_KILL_SECONDS', 3.5),
        'memory_mb' => (int) env('RUNNER_MEMORY_MB', 64),
        'output_bytes' => (int) env('RUNNER_OUTPUT_BYTES', 65536),
        'source_bytes' => (int) env('RUNNER_SOURCE_BYTES', 32768),
    ],

    'rate_limit' => [
        'max' => (int) env('RUNNER_RATE_MAX', 60),
        'decay_seconds' => (int) env('RUNNER_RATE_DECAY', 60),
    ],

    'max_pending_per_user' => (int) env('RUNNER_MAX_PENDING_USER', 2),

    'max_pending_global' => (int) env('RUNNER_MAX_PENDING_GLOBAL', 8),

];
