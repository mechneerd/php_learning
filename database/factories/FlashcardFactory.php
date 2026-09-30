<?php

namespace Database\Factories;

use App\Enums\CardType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flashcard>
 */
class FlashcardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'concept_id' => null,
            'lesson_id' => null,
            'card_type' => CardType::Definition,
            'front' => fake()->sentence(6),
            'back' => fake()->sentence(12),
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
