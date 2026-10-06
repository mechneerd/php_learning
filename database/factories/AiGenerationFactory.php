<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\AiGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_type' => 'lesson',
            'entity_id' => null,
            'stage' => 'lesson',
            'prompt_version' => 'v1',
            'model' => 'gpt-4o',
            'status' => GenerationStatus::Done,
            'tokens_in' => fake()->numberBetween(500, 4000),
            'tokens_out' => fake()->numberBetween(200, 2000),
            'cost' => fake()->randomFloat(4, 0.001, 0.05),
            'error' => null,
            'input_hash' => hash('sha256', fake()->unique()->uuid()),
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => GenerationStatus::Failed,
            'error' => 'rate limited',
            'cost' => 0,
        ]);
    }

    public function daysAgo(int $days): static
    {
        return $this->state(['created_at' => now()->subDays($days)->setTime(12, 0)]);
    }
}
