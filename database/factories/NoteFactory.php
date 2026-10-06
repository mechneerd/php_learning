<?php

namespace Database\Factories;

use App\Enums\NoteKind;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'noteable_type' => Lesson::class,
            'noteable_id' => Lesson::factory(),
            'kind' => NoteKind::Note,
            'body' => fake()->sentence(),
        ];
    }
}
