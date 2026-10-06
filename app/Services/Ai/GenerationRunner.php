<?php

namespace App\Services\Ai;

use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Pipeline A counterpart to PromptRunner (docs/10): the ai_generations
 * idempotency ledger, the shared daily generation budget, JSON retry-once
 * validation and token accounting for offline content generation.
 */
final class GenerationRunner
{
    public function __construct(
        private readonly AiClient $client,
        private readonly PromptRunner $helpers,
    ) {}

    /**
     * True when this exact prompt version + input already generated
     * successfully - callers skip without calling the provider.
     */
    public function done(string $task, string $inputHash): bool
    {
        return AiGeneration::query()
            ->where('stage', $task)
            ->where('prompt_version', $this->version($task))
            ->where('input_hash', $inputHash)
            ->where('status', GenerationStatus::Done->value)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  Closure(string): bool  $validate
     *
     * @throws BudgetExceeded when the shared daily budget is spent
     * @throws RuntimeException when the reply is still invalid after one retry
     */
    public function run(
        string $task,
        string $entityType,
        ?int $entityId,
        string $inputHash,
        string $system,
        string $prompt,
        Closure $validate,
        array $context = [],
    ): string {
        $tokensIn = $this->helpers->estimateTokens($system."\n".$prompt);
        $this->guardBudget($tokensIn);

        $context['task'] = $task;
        $context['system'] = $system;

        $generation = $this->begin($task, $entityType, $entityId, $inputHash);

        try {
            $content = $this->client->complete($prompt, $context);

            if (! $validate($content)) {
                $content = $this->client->complete(
                    $prompt."\n\nYour previous reply did not match the required JSON schema. "
                    .'Return only a valid JSON object that satisfies the schema - no prose, no code fences.',
                    $context,
                );
            }

            if (! $validate($content)) {
                throw new RuntimeException("AI reply for '{$task}' failed JSON validation after retry.");
            }

            if (trim($content) === '') {
                throw new RuntimeException("AI returned an empty response for '{$task}'.");
            }
        } catch (Throwable $e) {
            $generation->update([
                'status' => GenerationStatus::Failed,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }

        $generation->update([
            'status' => GenerationStatus::Done,
            'tokens_in' => $tokensIn,
            'tokens_out' => $this->helpers->estimateTokens($content),
        ]);

        return $content;
    }

    private function guardBudget(int $tokensIn): void
    {
        $budget = (int) config('ai.daily_generation_budget');

        $query = AiGeneration::query()->where('created_at', '>=', now()->startOfDay());

        $used = (int) $query->clone()->sum('tokens_in') + (int) $query->clone()->sum('tokens_out');

        if ($used + $tokensIn > $budget) {
            throw new BudgetExceeded($used, $budget);
        }
    }

    private function begin(string $task, string $entityType, ?int $entityId, string $inputHash): AiGeneration
    {
        $version = $this->version($task);

        // A previous failed attempt may be retried in place of its row.
        AiGeneration::query()
            ->where('stage', $task)
            ->where('prompt_version', $version)
            ->where('input_hash', $inputHash)
            ->where('status', GenerationStatus::Failed->value)
            ->delete();

        return AiGeneration::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'stage' => $task,
            'prompt_version' => $version,
            'model' => (string) config('ai.model_quality'),
            'status' => GenerationStatus::Running,
            'input_hash' => $inputHash,
        ]);
    }

    private function version(string $task): string
    {
        return (string) config("ai.prompt_versions.{$task}", 'v1');
    }
}
