<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ExerciseDifficulty;
use App\Enums\ExerciseType;
use App\Enums\ProvenanceSource;
use App\Models\Exercise;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'concept_id' => null,
            'type' => ExerciseType::Write,
            'difficulty' => ExerciseDifficulty::Easy,
            'prompt' => fake()->sentence(10),
            'starter_code' => "<?php\n\n// your code here\n",
            'solution_code' => "<?php\n\necho 'done';\n",
            'explanation' => fake()->sentence(),
            'expected_answer' => null,
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
