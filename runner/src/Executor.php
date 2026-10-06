<?php

namespace Runner;

/**
 * Runs submitted code in a separate PHP child process under the limits
 * from docs/12 §2: wall time (kill after grace), memory, disable_functions,
 * output cap. Never executes anything in this process.
 */
final class Executor
{
    /**
     * Child-only process/network/escape hatches (plus the static scan).
     *
     * @var list<string>
     */
    private const DISABLE_FUNCTIONS = [
        'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pclose', 'proc_terminate',
        'pcntl_exec', 'pcntl_fork', 'pcntl_signal', 'pcntl_alarm',
        'curl_init', 'curl_exec', 'curl_multi_exec',
        'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server',
        'socket_create', 'socket_connect', 'socket_bind',
        'dl', 'putenv', 'ini_set', 'ini_alter', 'mail',
        'symlink', 'link', 'apache_setenv',
    ];

    public function __construct(
        private readonly float $killSeconds,
        private readonly int $memoryMb,
        private readonly int $outputBytes,
    ) {}

    /**
     * @return array{status: string, stdout: string, stderr: string, exit_code: int|null, duration_ms: int, truncated: bool, oom: bool, error: string|null}
     */
    public function run(string $code): array
    {
        $command = [
            PHP_BINARY,
            '-d', 'memory_limit='.$this->memoryMb.'M',
            '-d', 'display_errors=stderr',
            '-d', 'error_reporting=E_ALL',
            '-d', 'disable_functions='.implode(',', self::DISABLE_FUNCTIONS),
        ];

        $env = getenv();
        // Skip the scan directory so loader noise (e.g. dev xdebug) never
        // reaches the learner's stderr; main php.ini extensions still load.
        $env['PHP_INI_SCAN_DIR'] = '';

        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $env);

        if (! is_resource($process)) {
            return $this->result('failed', '', '', null, 0, false, false, 'The runner could not start the PHP process.');
        }

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $truncated = false;
        $timedOut = false;
        $exitCode = null;
        $start = microtime(true);
        // Windows pipes ignore stream_set_blocking(); stream_select() is the
        // portable way to wait for output without deadlocking the wall kill.
        $open = [1 => $pipes[1], 2 => $pipes[2]];

        while (true) {
            if ($open !== []) {
                $read = array_values($open);
                $write = null;
                $except = null;
                $ready = @stream_select($read, $write, $except, 0, 100_000);

                if ($ready !== false && $ready > 0) {
                    foreach ($read as $stream) {
                        $chunk = fread($stream, 8192);

                        if ($chunk === '' || $chunk === false) {
                            if (feof($stream)) {
                                foreach ($open as $key => $candidate) {
                                    if ($candidate === $stream) {
                                        unset($open[$key]);
                                    }
                                }
                            }

                            continue;
                        }

                        $isStdout = $stream === $pipes[1];
                        $buffer = $isStdout ? $stdout : $stderr;
                        $remaining = $this->outputBytes - strlen($buffer);
                        $kept = $remaining > 0 ? substr($chunk, 0, $remaining) : '';

                        if (strlen($kept) < strlen($chunk)) {
                            $truncated = true;
                        }

                        if ($isStdout) {
                            $stdout .= $kept;
                        } else {
                            $stderr .= $kept;
                        }
                    }
                }
            } else {
                usleep(100_000);
            }

            $status = proc_get_status($process);

            if (! $status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if (microtime(true) - $start > $this->killSeconds) {
                $timedOut = true;
                proc_terminate($process);
                usleep(300_000);

                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }

                break;
            }
        }

        // Process has ended (or been killed): take the final buffered data.
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);

        $stdout .= $this->remainder($pipes[1], $stdout, $truncated);
        $stderr .= $this->remainder($pipes[2], $stderr, $truncated);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $stderr = $this->stripLoaderNoise($stderr);
        $oom = str_contains($stderr, 'Allowed memory size');

        if ($timedOut) {
            return $this->result('timeout', $stdout, $stderr, 124, $durationMs, $truncated, $oom, null);
        }

        if ($exitCode === null || $exitCode < 0) {
            $exitCode = 255;
        }

        return $this->result('ok', $stdout, $stderr, $exitCode, $durationMs, $truncated, $oom, null);
    }

    private function remainder($stream, string $buffer, bool &$truncated): string
    {
        $rest = (string) stream_get_contents($stream);

        if ($rest === '') {
            return '';
        }

        $remaining = $this->outputBytes - strlen($buffer);

        if ($remaining <= 0) {
            $truncated = true;

            return '';
        }

        if (strlen($rest) > $remaining) {
            $truncated = true;

            return substr($rest, 0, $remaining);
        }

        return $rest;
    }

    private function stripLoaderNoise(string $stderr): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $stderr) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Failed loading ')) {
                continue;
            }

            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * @return array{status: string, stdout: string, stderr: string, exit_code: int|null, duration_ms: int, truncated: bool, oom: bool, error: string|null}
     */
    private function result(
        string $status,
        string $stdout,
        string $stderr,
        ?int $exitCode,
        int $durationMs,
        bool $truncated,
        bool $oom,
        ?string $error,
    ): array {
        return [
            'status' => $status,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'truncated' => $truncated,
            'oom' => $oom,
            'error' => $error,
        ];
    }
}
