<?php

namespace Runner;

/**
 * Defense-in-depth source checks (docs/12 §2). The Laravel app runs the
 * same check before dispatch; the runner repeats it so a bypassed app
 * check still never reaches the child process.
 */
final class SourceGuard
{
    /**
     * Functions and constructs that must never appear in submitted code.
     *
     * @var list<string>
     */
    private const BANNED = [
        'eval', 'assert', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'curl_exec',
        'fsockopen', 'dl', 'putenv', 'mail',
    ];

    public function check(string $code): ?string
    {
        if (trim($code) === '') {
            return 'Source is empty.';
        }

        $hit = $this->bannedFunctionHit($code);

        if ($hit !== null) {
            return "Blocked: `{$hit}` is not allowed (sandbox rules).";
        }

        return null;
    }

    private function bannedFunctionHit(string $code): ?string
    {
        foreach (self::BANNED as $fn) {
            if (preg_match('/\b'.preg_quote($fn, '/').'\s*\(/i', $code) === 1) {
                return $fn;
            }
        }

        if (preg_match('/file_get_contents\s*\(\s*[\'"]https?:/i', $code) === 1) {
            return 'file_get_contents(http…)';
        }

        if (preg_match('/\b(include|require)(_once)?\s*\(?\s*[\'"]https?:/i', $code) === 1) {
            return 'remote include';
        }

        return null;
    }
}
