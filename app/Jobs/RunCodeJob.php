<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Models\CodeRun;
use App\Services\Execution\SandboxClient;
use App\Services\Execution\SandboxResult;
use App\Services\Execution\SandboxUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Executes one queued CodeRun against the sandbox runner and stores the
 * outcome with friendly timeout/OOM messages (docs/12 §2).
 */
class RunCodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $runId) {}

    public function handle(SandboxClient $client): void
    {
        $run = CodeRun::query()->find($this->runId);

        if ($run === null || $run->status !== RunStatus::Queued) {
            return;
        }

        $run->forceFill(['status' => RunStatus::Running])->save();

        try {
            $result = $client->run($run->code);
        } catch (SandboxUnavailableException) {
            $run->forceFill([
                'status' => RunStatus::Failed,
                'error' => 'The code runner is not available right now — please try again in a moment.',
            ])->save();

            return;
        } finally {
            $this->releaseGlobalSlot();
        }

        $this->persist($run, $result);
    }

    private function persist(CodeRun $run, SandboxResult $result): void
    {
        $wall = (float) config('runner.limits.wall_seconds');
        $memory = (int) config('runner.limits.memory_mb');
        $outputCap = (int) config('runner.limits.output_bytes');

        $status = $result->status;
        $error = $result->error;

        if ($result->oom) {
            $status = RunStatus::Failed;
            $error = "Ran out of memory ({$memory} MB limit) — a loop or collection is probably growing without bound.";
        } elseif ($status === RunStatus::Timeout) {
            $error = "Timed out after {$wall} seconds — your code probably runs forever. Check loops for a missing exit.";
        } elseif ($error === null && $status === RunStatus::Blocked) {
            $error = 'Blocked by the sandbox safety rules.';
        } elseif ($error === null && $status === RunStatus::Failed) {
            $error = 'The run failed inside the sandbox.';
        }

        $run->forceFill([
            'status' => $status,
            'stdout' => $this->cap($result->stdout, $outputCap),
            'stderr' => $this->cap($result->stderr, $outputCap),
            'exit_code' => $result->exitCode,
            'duration_ms' => $result->durationMs,
            'metrics' => ['truncated' => $result->truncated, 'oom' => $result->oom],
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
        ])->save();
    }

    private function cap(string $output, int $cap): string
    {
        if (strlen($output) <= $cap) {
            return $output;
        }

        return substr($output, 0, $cap)."\n[output truncated]";
    }

    private function releaseGlobalSlot(): void
    {
        $current = (int) Cache::get('runs:global:pending', 0);

        if ($current > 0) {
            Cache::put('runs:global:pending', $current - 1, now()->addSeconds(60));
        }
    }
}
