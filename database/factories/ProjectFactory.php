<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProjectLevel;
use App\Enums\ProvenanceSource;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage_id' => null,
            'level' => ProjectLevel::Beginner,
            'slug' => fake()->unique()->slug(3),
            'title' => rtrim(fake()->unique()->sentence(3), '.'),
            'brief' => fake()->paragraph(3),
            'requirements' => [
                'Covers the requested behaviour end to end',
                'Includes at least one test or assertion',
                'Follows the book\'s naming conventions',
            ],
            'solution_ref' => "Build the smallest working version first, then refactor once the tests are green.\nKeep I/O at the edges and the core logic pure so it stays testable.",
            'ord' => 1,
            'source' => ProvenanceSource::Ai,
            'status' => ContentStatus::Published,
            'page_printed_from' => null,
            'page_printed_to' => null,
            'page_pdf_from' => null,
            'page_pdf_to' => null,
            'ai_model' => 'gpt-4o',
            'ai_generated_at' => now(),
            'ai_prompt_version' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'is_outdated' => false,
        ];
    }

    public function onStage(Stage $stage): static
    {
        return $this->state(fn (): array => ['stage_id' => $stage->id]);
    }
}
