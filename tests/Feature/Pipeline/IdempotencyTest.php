<?php

use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use App\Models\Lesson;
use App\Services\Ai\AiClient;
use App\Services\Ai\Generators\CardsGenerator;
use App\Services\Ai\PromptRunner;
use App\Services\Ai\StubAiClient;

/**
 * Wraps the stub provider and counts how often it is actually called.
 */
final class IdempotencyCountingClient implements AiClient
{
    public int $calls = 0;

    private StubAiClient $stub;

    public function __construct()
    {
        $this->stub = new StubAiClient;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $prompt, array $context = []): string
    {
        $this->calls++;

        return $this->stub->complete($prompt, $context);
    }
}

it('skips the second generation for identical input and never duplicates rows', function () {
    $client = new IdempotencyCountingClient;
    $this->app->instance(AiClient::class, $client);

    $generator = app(CardsGenerator::class);
    $lesson = Lesson::factory()->create();

    $first = $generator->generate($lesson, ['variables', 'types']);

    expect($first)->not->toBeNull()
        ->and($client->calls)->toBe(1)
        ->and(AiGeneration::query()->where('stage', 'cards')->count())->toBe(1)
        ->and(AiGeneration::query()->where('stage', 'cards')->sole()->status->value)->toBe('done');

    $second = $generator->generate($lesson, ['variables', 'types']);

    expect($second)->toBeNull()
        ->and($client->calls)->toBe(1)
        ->and(AiGeneration::query()->where('stage', 'cards')->count())->toBe(1);
});

it('generates again when the input changes', function () {
    $client = new IdempotencyCountingClient;
    $this->app->instance(AiClient::class, $client);

    $generator = app(CardsGenerator::class);
    $lesson = Lesson::factory()->create();

    expect($generator->generate($lesson, ['variables']))->not->toBeNull()
        ->and($generator->generate($lesson, ['types']))->not->toBeNull()
        ->and($client->calls)->toBe(2)
        ->and(AiGeneration::query()->where('stage', 'cards')->count())->toBe(2);
});

it('replaces a failed row on retry instead of violating the unique constraint', function () {
    $client = new IdempotencyCountingClient;
    $this->app->instance(AiClient::class, $client);

    $generator = app(CardsGenerator::class);
    $lesson = Lesson::factory()->create();
    $slugs = ['variables', 'types'];

    $hash = app(PromptRunner::class)->hashFor(
        'cards',
        [(string) $lesson->id, $lesson->slug, implode(',', $slugs)],
    );

    AiGeneration::query()->create([
        'entity_type' => 'flashcard',
        'entity_id' => $lesson->id,
        'stage' => 'cards',
        'prompt_version' => (string) config('ai.prompt_versions.cards', 'v1'),
        'model' => 'test',
        'status' => GenerationStatus::Failed,
        'input_hash' => $hash,
        'error' => 'previous run crashed',
    ]);

    $recovered = $generator->generate($lesson, $slugs);

    expect($recovered)->not->toBeNull()
        ->and($client->calls)->toBe(1)
        ->and(AiGeneration::query()->where('stage', 'cards')->count())->toBe(1)
        ->and(AiGeneration::query()->where('stage', 'cards')->sole()->status->value)->toBe('done');
});
