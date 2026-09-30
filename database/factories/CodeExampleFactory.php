<?php

namespace Database\Factories;

use App\Enums\CodeTier;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\CodeExample;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeExample>
 */
class CodeExampleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'concept_id' => null,
            'listing_ref' => null,
            'tier' => CodeTier::Core,
            'title' => fake()->sentence(3),
            'code' => "<?php\n\necho 'hello';\n",
            'expected_output' => 'hello',
            'explanation' => fake()->sentence(),
            'syntax_notes' => null,
            'common_mistake' => null,
            'external_ref' => null,
            'ord' => 1,
            'source' => ProvenanceSource::Book,
            'status' => ContentStatus::Published,
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
