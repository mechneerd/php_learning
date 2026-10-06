<?php

namespace App\Services\Ai;

use App\Models\AiMessage;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Hashing, logging inputs, retries and token accounting around the raw
 * AiClient (docs/10). Enforces the per-user daily token budget.
 */
final class PromptRunner
{
    public function __construct(private readonly AiClient $client) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  (Closure(string): bool)|null  $validate
     */
    public function run(
        User $user,
        string $task,
        string $system,
        string $prompt,
        array $context = [],
        ?Closure $validate = null,
    ): AiResult {
        $context['task'] = $task;
        $context['system'] = $system;

        $tokensIn = $this->estimateTokens($system."\n".$prompt);
        $budget = (int) config('ai.daily_token_budget');
        $used = $this->tokensUsedToday($user);

        if ($used + $tokensIn > $budget) {
            throw new BudgetExceeded($used, $budget);
        }

        $content = $this->client->complete($prompt, $context);

        if ($validate !== null && ! $validate($content)) {
            $content = $this->client->complete(
                $prompt."\n\nYour previous reply did not match the required format. Return only a valid response.",
                $context,
            );

            if (! $validate($content)) {
                throw new RuntimeException('AI response failed validation after retry.');
            }
        }

        if (trim($content) === '') {
            throw new RuntimeException('AI returned an empty response.');
        }

        return new AiResult($content, $tokensIn, $this->estimateTokens($content));
    }

    /**
     * Idempotency hash: bumping ai.prompt_versions.{task} changes the hash.
     *
     * @param  list<string>  $parts
     */
    public function hashFor(string $task, array $parts): string
    {
        $version = (string) config("ai.prompt_versions.{$task}", 'v1');

        return sha1($task.'|'.$version.'|'.implode('|', $parts));
    }

    public function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    private function tokensUsedToday(User $user): int
    {
        $row = AiMessage::query()
            ->whereHas('conversation', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where('created_at', '>=', today())
            ->selectRaw('COALESCE(SUM(tokens_in + tokens_out), 0) as total')
            ->toBase()
            ->first();

        return (int) ($row->total ?? 0);
    }
}
