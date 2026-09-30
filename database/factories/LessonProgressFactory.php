<?php

namespace Database\Factories;

use App\Enums\ProgressState;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'state' => ProgressState::Opened,
            'active_seconds' => 0,
            'opened_at' => now(),
            'last_at' => now(),
        ];
    }
}
