<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ExerciseDifficulty;
use App\Enums\ProvenanceSource;
use App\Models\InterviewQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewQuestion>
 */
class InterviewQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'concept_id' => null,
            'topic' => 'oop',
            'question' => fake()->sentence(12).'?',
            'model_answer' => fake()->paragraph(3),
            'follow_ups' => [
                'Where would that break in real code?',
                'How would you test it?',
            ],
            'difficulty' => ExerciseDifficulty::Medium,
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
