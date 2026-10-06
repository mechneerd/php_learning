<?php

namespace App\Services\Execution;

use App\Enums\RunStatus;

/**
 * Parsed result of one sandbox run (mirrors the runner JSON contract).
 */
final readonly class SandboxResult
{
    public function __construct(
        public RunStatus $status,
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
        public int $durationMs,
        public bool $truncated,
        public bool $oom,
        public ?string $error,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $status = match ($payload['status'] ?? null) {
            'ok' => RunStatus::Done,
            'timeout' => RunStatus::Timeout,
            'blocked' => RunStatus::Blocked,
            default => RunStatus::Failed,
        };

        $error = $payload['error'] ?? null;

        return new self(
            status: $status,
            stdout: (string) ($payload['stdout'] ?? ''),
            stderr: (string) ($payload['stderr'] ?? ''),
            exitCode: isset($payload['exit_code']) ? (int) $payload['exit_code'] : null,
            durationMs: (int) ($payload['duration_ms'] ?? 0),
            truncated: (bool) ($payload['truncated'] ?? false),
            oom: (bool) ($payload['oom'] ?? false),
            error: is_string($error) && $error !== '' ? $error : null,
        );
    }

    public static function blocked(string $reason): self
    {
        return new self(
            status: RunStatus::Blocked,
            stdout: '',
            stderr: '',
            exitCode: null,
            durationMs: 0,
            truncated: false,
            oom: false,
            error: $reason,
        );
    }
}
