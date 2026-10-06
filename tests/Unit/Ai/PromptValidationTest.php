<?php

use App\Models\AiGeneration;
use App\Services\Ai\AiClient;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Scripted provider: replies in order, records every prompt it saw.
 */
final class ValidationScriptedClient implements AiClient
{
    public int $calls = 0;

    /** @var list<string> */
    public array $prompts = [];

    /**
     * @param  list<string>  $replies
     */
    public function __construct(private array $replies) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $prompt, array $context = []): string
    {
        $this->calls++;
        $this->prompts[] = $prompt;

        return array_shift($this->replies) ?? '[]';
    }
}

function validationRunner(ValidationScriptedClient $client): GenerationRunner
{
    return new GenerationRunner($client, new PromptRunner($client));
}

function validJson(string $reply): bool
{
    return is_array(json_decode($reply, true));
}

it('retries once with a correction and records a done generation', function () {
    $client = new ValidationScriptedClient(['not json at all', '{"cards": [{"front": "a", "back": "b"}]}']);
    $runner = validationRunner($client);

    $content = $runner->run(
        'cards',
        'flashcard',
        7,
        'hash-valid-json',
        'system prompt',
        'lesson prompt',
        validJson(...),
    );

    expect($client->calls)->toBe(2)
        ->and($client->prompts[1])->toContain('did not match the required JSON schema')
        ->and($content)->toBe('{"cards": [{"front": "a", "back": "b"}]}');

    $generation = AiGeneration::sole();

    expect($generation->stage)->toBe('cards')
        ->and($generation->status->value)->toBe('done')
        ->and($generation->tokens_in)->toBeGreaterThan(0)
        ->and($generation->tokens_out)->toBeGreaterThan(0)
        ->and($generation->error)->toBeNull();
});

it('throws and marks the generation failed when both replies are invalid', function () {
    $client = new ValidationScriptedClient(['still not json', 'also not json']);
    $runner = validationRunner($client);

    expect(fn () => $runner->run(
        'cards',
        'flashcard',
        7,
        'hash-invalid-json',
        'system prompt',
        'lesson prompt',
        validJson(...),
    ))->toThrow(RuntimeException::class, "AI reply for 'cards' failed JSON validation after retry.");

    expect($client->calls)->toBe(2);

    $generation = AiGeneration::sole();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error)->toContain('failed JSON validation');
});

it('skips work without calling the provider when the input already generated', function () {
    $client = new ValidationScriptedClient(['{"ok": 1}']);
    $runner = validationRunner($client);

    $runner->run('cards', 'flashcard', 7, 'hash-done-once', 'system', 'prompt', validJson(...));

    expect($runner->done('cards', 'hash-done-once'))->toBeTrue()
        ->and($runner->done('cards', 'hash-other-input'))->toBeFalse()
        ->and($client->calls)->toBe(1)
        ->and(AiGeneration::query()->count())->toBe(1);
});
