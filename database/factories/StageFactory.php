<?php

namespace Database\Factories;

use App\Enums\StageSource;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(0, 11),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'subtitle' => null,
            'source' => StageSource::Book,
            'description' => fake()->sentence(),
            'gate_rules' => null,
            'ord' => 0,
        ];
    }
}
