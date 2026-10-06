<?php

namespace App\Services\Execution;

/**
 * Static pre-check for banned functions in submitted code (docs/12 §2).
 * Runs in the app before dispatch and again inside the runner.
 */
final class BannedFunctionCheck
{
    /**
     * Functions that must never appear in a submitted answer.
     *
     * @var list<string>
     */
    private const BANNED = [
        'eval', 'assert', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'curl_exec',
        'fsockopen', 'dl', 'putenv', 'mail',
    ];

    /**
     * First banned function found in the submission, or null.
     */
    public function firstHit(string $code): ?string
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
