<?php

namespace Database\Factories;

use App\Enums\MasteryLevel;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptMastery>
 */
class ConceptMasteryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'concept_id' => Concept::factory(),
            'level' => MasteryLevel::Unseen,
            'evidence' => [],
            'mastered_at' => null,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    public function withEvidence(array $keys): static
    {
        return $this->state(fn (): array => [
            'evidence' => array_fill_keys($keys, 1),
        ]);
    }

    public function mastered(): static
    {
        return $this->state(fn (): array => [
            'level' => MasteryLevel::Mastered,
            'evidence' => array_fill_keys(['read', 'recall', 'easy', 'medium', 'debug', 'mixed'], 1),
            'mastered_at' => now(),
        ]);
    }
}
