<?php

namespace Database\Factories;

use App\Enums\MasteryLevel;
use App\Enums\SkillDomain;
use App\Models\SkillProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillProgress>
 */
class SkillProgressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'skill_domain' => SkillDomain::Oop->value,
            'level' => MasteryLevel::Unseen,
            'mastered_count' => 0,
            'comfortable_count' => 0,
        ];
    }
}
