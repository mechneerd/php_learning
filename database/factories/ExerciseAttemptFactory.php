<?php

namespace Database\Factories;

use App\Enums\AttemptResult;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseAttempt>
 */
class ExerciseAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_id' => Exercise::factory(),
            'code' => "<?php\n\necho 'ok';\n",
            'result' => AttemptResult::Correct,
            'hints_used' => fake()->numberBetween(0, 3),
            'duration_sec' => fake()->numberBetween(10, 600),
            'test_results' => null,
            'created_at' => now(),
        ];
    }

    public function incorrect(): static
    {
        return $this->state(['result' => AttemptResult::Incorrect]);
    }

    public function partial(): static
    {
        return $this->state(['result' => AttemptResult::Partial]);
    }

    public function daysAgo(int $days): static
    {
        return $this->state(['created_at' => now()->subDays($days)]);
    }
}
