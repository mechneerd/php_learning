<?php

/**
 * Runner front controller — serve with:
 *
 *   RUNNER_SECRET=change-me php -S 127.0.0.1:8090 runner/router.php
 *
 * POST /run with signed headers executes code under the limits from
 * docs/12 §2. Zero dependencies; no shared filesystem with the app.
 */

require __DIR__.'/src/Signature.php';
require __DIR__.'/src/SourceGuard.php';
require __DIR__.'/src/Executor.php';

use Runner\Executor;
use Runner\Signature;
use Runner\SourceGuard;

/**
 * @param  array<string, mixed>  $payload
 */
function respond(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $path === '/health') {
    respond(200, ['ok' => true]);
}

if ($path !== '/run') {
    respond(404, ['error' => 'Not found.']);
}

if ($method !== 'POST') {
    respond(405, ['error' => 'POST required.']);
}

$secret = getenv('RUNNER_SECRET') ?: '';

if ($secret === '') {
    respond(500, ['error' => 'RUNNER_SECRET is not configured.']);
}

$sourceBytes = (int) (getenv('RUNNER_SOURCE_BYTES') ?: 32768);
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($contentLength > $sourceBytes + 512) {
    respond(413, ['status' => 'blocked', 'error' => "Source exceeds {$sourceBytes} bytes."]);
}

$body = (string) file_get_contents('php://input');

$signature = new Signature($secret);
$timestamp = $_SERVER['HTTP_X_RUNNER_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_RUNNER_SIGNATURE'] ?? '';

if (! $signature->verify($timestamp, $provided, $body)) {
    respond(403, ['error' => 'Invalid or expired request signature.']);
}

$payload = json_decode($body, true);

if (! is_array($payload) || ! isset($payload['code']) || ! is_string($payload['code'])) {
    respond(422, ['error' => 'Missing "code" in JSON body.']);
}

$code = $payload['code'];

if (strlen($code) > $sourceBytes) {
    respond(413, ['status' => 'blocked', 'error' => "Source exceeds {$sourceBytes} bytes."]);
}

$guard = new SourceGuard;
$reason = $guard->check($code);

if ($reason !== null) {
    respond(200, [
        'status' => 'blocked',
        'error' => $reason,
        'stdout' => '',
        'stderr' => '',
        'exit_code' => null,
        'duration_ms' => 0,
        'truncated' => false,
        'oom' => false,
    ]);
}

$wallSeconds = (float) (getenv('RUNNER_WALL_SECONDS') ?: 3);
$killSeconds = (float) (getenv('RUNNER_KILL_SECONDS') ?: $wallSeconds + 0.5);
$memoryMb = (int) (getenv('RUNNER_MEMORY_MB') ?: 64);
$outputBytes = (int) (getenv('RUNNER_OUTPUT_BYTES') ?: 65536);

$executor = new Executor($killSeconds, $memoryMb, $outputBytes);

respond(200, $executor->run($code));
