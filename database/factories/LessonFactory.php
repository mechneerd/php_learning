<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'chapter_id' => null,
            'section_id' => null,
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(4),
            'summary' => fake()->sentence(),
            'status' => ContentStatus::Draft,
            'est_minutes' => 5,
            'ord' => 0,
            'source' => ProvenanceSource::Book,
            'page_printed_from' => null,
            'page_printed_to' => null,
            'page_pdf_from' => null,
            'page_pdf_to' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_prompt_version' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'is_outdated' => false,
        ];
    }
}
