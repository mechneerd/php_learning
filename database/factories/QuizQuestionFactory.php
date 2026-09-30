<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Enums\QuizQuestionType;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'concept_id' => null,
            'type' => QuizQuestionType::Mcq,
            'stem' => fake()->sentence(10),
            'explanation' => fake()->sentence(),
            'difficulty' => 'easy',
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
}
