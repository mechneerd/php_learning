<?php

namespace Runner;

/**
 * HMAC request signing shared contract with the Laravel app
 * (App\Services\Execution\SandboxClient signs identically).
 */
final class Signature
{
    public function __construct(private readonly string $secret) {}

    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".$body, $this->secret);
    }

    public function verify(string $timestamp, string $signature, string $body, int $ttl = 60): bool
    {
        if ($this->secret === '' || $signature === '') {
            return false;
        }

        if (preg_match('/^\d{10}$/', $timestamp) !== 1) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $ttl) {
            return false;
        }

        return hash_equals($this->sign($timestamp, $body), $signature);
    }
}
