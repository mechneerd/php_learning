<?php

/**
 * Runner limits tests (docs/12 §2) — no framework, zero dependencies.
 *
 *   php runner/tests/limits_test.php
 *
 * Boots the runner with small test limits, then asserts signing,
 * banned functions, wall kill, output cap, OOM, and source size.
 */
$secret = 'test-secret';

$checks = 0;
$failures = 0;

function check(string $label, bool $condition): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "PASS {$label}\n";
    } else {
        $failures++;
        echo "FAIL {$label}\n";
    }
}

/**
 * @param  list<string>  $headers
 * @return array{0: int, 1: string}
 */
function httpPost(string $url, string $body, array $headers): array
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);

    $response = @file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#HTTP/\S+\s+(\d+)#', $line, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return [$status, (string) $response];
}

/**
 * @return list<string>
 */
function signedHeaders(string $body, ?int $timestamp = null): array
{
    global $secret;

    $ts = (string) ($timestamp ?? time());
    $sig = hash_hmac('sha256', $ts."\n".$body, $secret);

    return ['Content-Type: application/json', "X-Runner-Timestamp: {$ts}", "X-Runner-Signature: {$sig}"];
}

function jsonResponse(string $body): array
{
    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : [];
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($socket === false) {
    echo "FAIL could not allocate a test port: {$errstr}\n";
    exit(1);
}
$name = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr((string) $name, strrpos((string) $name, ':') + 1);

$baseUrl = "http://127.0.0.1:{$port}";
$logFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'runner-test-'.getmypid().'.log';

$command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/../router.php'];
$env = array_merge(getenv(), [
    'RUNNER_SECRET' => $secret,
    'RUNNER_WALL_SECONDS' => '1',
    'RUNNER_KILL_SECONDS' => '1.5',
    'RUNNER_MEMORY_MB' => '16',
    'RUNNER_OUTPUT_BYTES' => '2048',
    'RUNNER_SOURCE_BYTES' => '4096',
]);

$server = proc_open($command, [
    0 => ['file', 'NUL', 'r'],
    1 => ['file', $logFile, 'a'],
    2 => ['file', $logFile, 'a'],
], $pipes, null, $env);

if (! is_resource($server)) {
    echo "FAIL could not start the runner server\n";
    exit(1);
}

$ready = false;

for ($i = 0; $i < 100; $i++) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

    if ($probe !== false) {
        fclose($probe);
        $ready = true;
        break;
    }

    usleep(100_000);
}

if (! $ready) {
    proc_terminate($server);
    proc_close($server);
    echo "FAIL runner server did not become ready\n";
    exit(1);
}

try {
    // 1. Health endpoint.
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]]);
    $health = @file_get_contents("{$baseUrl}/health", false, $context);
    check('health endpoint responds', $health !== false && str_contains((string) $health, '"ok":true'));

    // 2. Valid signed run executes.
    $body = json_encode(['code' => '<?php echo "hello-". (1 + 1);']);
    [$status, $response] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    $payload = jsonResponse($response);
    check('valid run returns 200', $status === 200);
    check('valid run status ok', ($payload['status'] ?? '') === 'ok');
    check('valid run stdout', ($payload['stdout'] ?? '') === 'hello-2');
    check('valid run exit code 0', ($payload['exit_code'] ?? -1) === 0);
    check('valid run stderr has no loader noise', ! str_contains((string) ($payload['stderr'] ?? ''), 'Failed loading'));

    // 3. Missing / wrong / stale signatures are rejected.
    [$status] = httpPost("{$baseUrl}/run", $body, ['Content-Type: application/json']);
    check('missing signature is rejected', $status === 403);

    [$status] = httpPost("{$baseUrl}/run", $body, [
        'Content-Type: application/json',
        'X-Runner-Timestamp: '.time(),
        'X-Runner-Signature: deadbeef',
    ]);
    check('wrong signature is rejected', $status === 403);

    [$status] = httpPost("{$baseUrl}/run", $body, signedHeaders($body, time() - 3600));
    check('stale timestamp is rejected', $status === 403);

    // 4. Defense-in-depth banned function scan.
    $body = json_encode(['code' => '<?php system("whoami");']);
    [$status, $response] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    $payload = jsonResponse($response);
    check('banned function returns 200 with blocked status', $status === 200 && ($payload['status'] ?? '') === 'blocked');
    check('banned function never executed', ($payload['stdout'] ?? '') === '' && ($payload['exit_code'] ?? null) === null);

    // 5. Wall clock kill (server wall=1s, kill=1.5s).
    $body = json_encode(['code' => '<?php while (true) {}']);
    $started = microtime(true);
    [$status, $response] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    $elapsed = microtime(true) - $started;
    $payload = jsonResponse($response);
    check('infinite loop status timeout', ($payload['status'] ?? '') === 'timeout');
    check('infinite loop killed within budget', $elapsed < 4.0);
    check('infinite loop exit code 124', ($payload['exit_code'] ?? null) === 124);

    // 6. Output cap (server cap = 2048 bytes).
    $body = json_encode(['code' => '<?php for ($i = 0; $i < 10000; $i++) { echo "x"; }']);
    [, $response] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    $payload = jsonResponse($response);
    check('oversized output is truncated', ($payload['truncated'] ?? false) === true && strlen((string) ($payload['stdout'] ?? '')) <= 2048);

    // 7. Memory limit (server memory = 16 MB).
    $body = json_encode(['code' => '<?php $a = str_repeat("x", 32 * 1024 * 1024); echo strlen($a);']);
    [, $response] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    $payload = jsonResponse($response);
    check('memory exhaustion flagged as oom', ($payload['oom'] ?? false) === true);
    check('memory exhaustion exits non-zero', ($payload['exit_code'] ?? 0) !== 0);

    // 8. Source size (server cap = 4096 bytes).
    $body = json_encode(['code' => '<?php // '.str_repeat('a', 5000)]);
    [$status] = httpPost("{$baseUrl}/run", $body, signedHeaders($body));
    check('oversized source is rejected with 413', $status === 413);

    // 9. Wrong methods / paths.
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]]);
    @file_get_contents("{$baseUrl}/run", false, $context);
    $getLine = $http_response_header[0] ?? '';
    check('GET /run is rejected', str_contains($getLine, '405'));
} finally {
    proc_terminate($server);
    proc_close($server);

    if (is_file($logFile)) {
        @unlink($logFile);
    }
}

echo "\n{$checks} checks, {$failures} failure(s)\n";
exit($failures > 0 ? 1 : 0);
