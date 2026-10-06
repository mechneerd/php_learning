<?php

namespace Database\Factories;

use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewItem>
 */
class ReviewItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_type' => ReviewItemType::Card,
            'item_id' => fake()->unique()->numberBetween(1, 99999),
            'concept_id' => null,
            'reason' => 'due_card',
            'due_at' => now(),
            'status' => ReviewItemStatus::Due,
        ];
    }
}
