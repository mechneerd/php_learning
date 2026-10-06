<?php

use App\Enums\GenerationStatus;
use App\Jobs\GenerateLessonJob;
use App\Models\AiGeneration;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Stage;
use App\Services\Ai\AiClient;
use App\Services\Ai\BudgetExceeded;
use App\Services\Ai\Generators\LessonGenerator;
use App\Services\Ai\StubAiClient;
use App\Services\Content\BlockValidator;

/**
 * Wraps the stub provider and counts how often it is actually called.
 */
final class BudgetCountingClient implements AiClient
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

function spendToday(int $tokensIn, int $tokensOut): void
{
    AiGeneration::query()->create([
        'entity_type' => 'lesson',
        'entity_id' => null,
        'stage' => 'warmup',
        'prompt_version' => 'v1',
        'model' => 'test',
        'status' => GenerationStatus::Done,
        'input_hash' => 'spent-'.uniqid('', true),
        'tokens_in' => $tokensIn,
        'tokens_out' => $tokensOut,
    ]);
}

it('refuses to generate once the daily generation budget is spent', function () {
    config(['ai.daily_generation_budget' => 40]);

    spendToday(tokensIn: 30, tokensOut: 25); // 55 of 40 already used

    $client = new BudgetCountingClient;
    $this->app->instance(AiClient::class, $client);

    $section = Section::factory()->create();
    $stage = Stage::factory()->create();

    $job = new GenerateLessonJob($section->id, $stage->id);

    expect(fn () => $job->handle(app(LessonGenerator::class), app(BlockValidator::class)))
        ->toThrow(BudgetExceeded::class);

    expect($client->calls)->toBe(0)
        ->and(Lesson::query()->where('section_id', $section->id)->exists())->toBeFalse()
        ->and(AiGeneration::query()->where('stage', 'lesson')->count())->toBe(0);
});

it('allows generation while the budget remains', function () {
    config(['ai.daily_generation_budget' => 500000]);

    spendToday(tokensIn: 10, tokensOut: 5);

    $client = new BudgetCountingClient;
    $this->app->instance(AiClient::class, $client);

    $section = Section::factory()->create();
    $stage = Stage::factory()->create();

    $job = new GenerateLessonJob($section->id, $stage->id);
    $job->handle(app(LessonGenerator::class), app(BlockValidator::class));

    expect($client->calls)->toBeGreaterThanOrEqual(1)
        ->and(Lesson::query()->where('section_id', $section->id)->exists())->toBeTrue()
        ->and(AiGeneration::query()->where('stage', 'lesson')->where('status', GenerationStatus::Done->value)->count())->toBe(1);
});
