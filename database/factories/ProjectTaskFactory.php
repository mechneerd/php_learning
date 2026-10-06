<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'ord' => 1,
            'brief' => fake()->sentence(12),
            'concept_ids' => ['class'],
            'solution_hint' => fake()->sentence(8),
        ];
    }
}
