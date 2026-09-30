<?php

namespace Database\Factories;

use App\Enums\ConceptGranularity;
use App\Enums\ContentSource;
use App\Enums\ContentStatus;
use App\Models\Concept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Concept>
 */
class ConceptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => rtrim(fake()->unique()->sentence(3), '.'),
            'definition' => fake()->sentence(),
            'skill_domain' => 'oop',
            'granularity' => ConceptGranularity::Concept,
            'is_core' => false,
            'source' => ContentSource::Ai,
            'status' => ContentStatus::Published,
        ];
    }
}
