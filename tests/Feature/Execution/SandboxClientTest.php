<?php

use App\Enums\RunStatus;
use App\Services\Execution\SandboxClient;
use App\Services\Execution\SandboxUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'runner.secret' => 'test-secret',
        'runner.url' => 'http://runner.test',
    ]);
});

function sandboxOkPayload(array $overrides = []): array
{
    return array_merge([
        'status' => 'ok',
        'stdout' => 'hello',
        'stderr' => '',
        'exit_code' => 0,
        'duration_ms' => 12,
        'truncated' => false,
        'oom' => false,
        'error' => null,
    ], $overrides);
}

it('signs each request with the configured secret', function () {
    Http::fake(['*' => Http::response(sandboxOkPayload())]);

    (new SandboxClient)->run('<?php echo "hello";');

    Http::assertSent(function ($request) {
        $timestamp = $request->header('X-Runner-Timestamp')[0] ?? '';
        $provided = $request->header('X-Runner-Signature')[0] ?? '';
        $expected = hash_hmac('sha256', $timestamp."\n".$request->body(), 'test-secret');

        return $request->url() === 'http://runner.test/run'
            && $provided === $expected
            && preg_match('/^\d{10}$/', $timestamp) === 1;
    });
});

it('parses a successful run', function () {
    Http::fake(['*' => Http::response(sandboxOkPayload())]);

    $result = (new SandboxClient)->run('<?php echo "hello";');

    expect($result->status)->toBe(RunStatus::Done)
        ->and($result->stdout)->toBe('hello')
        ->and($result->exitCode)->toBe(0)
        ->and($result->durationMs)->toBe(12)
        ->and($result->error)->toBeNull();
});

it('parses timeout and blocked responses', function () {
    Http::fake([
        'http://runner.test/run' => Http::sequence()
            ->push(sandboxOkPayload(['status' => 'timeout', 'exit_code' => 124]))
            ->push(sandboxOkPayload(['status' => 'blocked', 'error' => 'Blocked: `eval` is not allowed (sandbox rules).', 'exit_code' => null, 'stdout' => ''])),
    ]);

    $client = new SandboxClient;
    $timeout = $client->run('<?php while(true){}');
    $blocked = $client->run('<?php eval($x);');

    expect($timeout->status)->toBe(RunStatus::Timeout)
        ->and($timeout->exitCode)->toBe(124)
        ->and($blocked->status)->toBe(RunStatus::Blocked)
        ->and($blocked->error)->toContain('eval');
});

it('maps a 413 to a blocked result without treating it as an outage', function () {
    Http::fake(['*' => Http::response(['status' => 'blocked', 'error' => 'Source exceeds the 32768 byte limit.'], 413)]);

    $result = (new SandboxClient)->run('<?php // big');

    expect($result->status)->toBe(RunStatus::Blocked)
        ->and($result->error)->toContain('32768');
});

it('refuses oversized source without calling the runner', function () {
    Http::fake();
    config(['runner.limits.source_bytes' => 16]);

    $result = (new SandboxClient)->run('<?php echo "this source is definitely larger than 16 bytes";');

    expect($result->status)->toBe(RunStatus::Blocked)
        ->and($result->error)->toContain('16 byte');
    Http::assertNothingSent();
});

it('throws when the runner is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

    expect(fn () => (new SandboxClient)->run('<?php echo 1;'))
        ->toThrow(SandboxUnavailableException::class, 'not reachable');
});

it('throws on non-2xx runner responses', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn () => (new SandboxClient)->run('<?php echo 1;'))
        ->toThrow(SandboxUnavailableException::class, 'HTTP 500');
});

it('fails closed when no secret is configured', function () {
    config(['runner.secret' => '']);
    Http::fake();

    expect(fn () => (new SandboxClient)->run('<?php echo 1;'))
        ->toThrow(SandboxUnavailableException::class, 'RUNNER_SECRET');
    Http::assertNothingSent();
});
