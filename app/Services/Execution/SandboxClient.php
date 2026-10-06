<?php

namespace App\Services\Execution;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Signed HTTP client for the standalone runner service (docs/12 §2).
 * The web process never spawns PHP for user code — it posts the source
 * here and gets stdout/stderr back inline.
 */
final class SandboxClient
{
    /**
     * @throws SandboxUnavailableException when the runner cannot be used
     */
    public function run(string $code): SandboxResult
    {
        $secret = config('runner.secret');

        if (! is_string($secret) || $secret === '') {
            throw new SandboxUnavailableException('RUNNER_SECRET is not configured.');
        }

        $sourceBytes = (int) config('runner.limits.source_bytes');

        if (strlen($code) > $sourceBytes) {
            return SandboxResult::blocked("Source exceeds the {$sourceBytes} byte limit.");
        }

        try {
            $body = json_encode(['code' => $code], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SandboxUnavailableException('The submission could not be encoded for the runner.', 0, $e);
        }

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp."\n".$body, $secret);
        $url = rtrim((string) config('runner.url'), '/').'/run';

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-Runner-Timestamp' => $timestamp,
                    'X-Runner-Signature' => $signature,
                ])
                ->timeout((float) config('runner.timeout'))
                ->post($url);
        } catch (ConnectionException|RequestException $e) {
            throw new SandboxUnavailableException('The code runner is not reachable right now.', 0, $e);
        }

        if ($response->status() === 413) {
            $error = $response->json('error');

            return SandboxResult::blocked(is_string($error) && $error !== '' ? $error : 'Source is too large.');
        }

        if (! $response->successful()) {
            throw new SandboxUnavailableException("The code runner rejected the request (HTTP {$response->status()}).");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SandboxUnavailableException('The code runner returned an unreadable response.');
        }

        return SandboxResult::fromPayload($payload);
    }
}
