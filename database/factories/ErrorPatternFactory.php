<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ErrorCategory;
use App\Enums\ProvenanceSource;
use App\Models\ErrorPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorPattern>
 */
class ErrorPatternFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => rtrim(fake()->unique()->sentence(4), '.'),
            'category' => ErrorCategory::Runtime,
            'symptom' => fake()->sentence(14),
            'cause' => fake()->sentence(16),
            'identify_steps' => [
                'Read the first file and line number in the message',
                'Reproduce with the smallest possible input',
                'Trace the value back to where it was set',
            ],
            'fix_steps' => [
                'Guard the failing path',
                'Add a regression test that reproduces the bug',
                'Re-run the suite',
            ],
            'prevent_steps' => [
                'Validate at the boundary',
                'Let types and assertions fail fast',
            ],
            'practice_ref' => null,
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
