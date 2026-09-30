<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\ExerciseType;
use App\Models\Exercise;

/**
 * Maps activity to mastery evidence keys (docs/04 §8). Promote/demote
 * rules are finalized in Phase 5 with concept_mastery writes; this class
 * introduces the key vocabulary here.
 */
final class MasteryEvaluator
{
    public const READ = 'read';

    public const RECALL = 'recall';

    public const EASY = 'easy';

    public const MEDIUM = 'medium';

    public const DEBUG = 'debug';

    public const MIXED = 'mixed';

    /**
     * Evidence keys earned by a correct attempt on this exercise.
     *
     * @return list<string>
     */
    public function evidenceKeysForExercise(Exercise $exercise, AttemptResult $result): array
    {
        if ($result !== AttemptResult::Correct) {
            return [];
        }

        $keys = [];

        if ($exercise->difficulty->value === 'easy') {
            $keys[] = self::EASY;
        }

        if ($exercise->difficulty->value === 'medium') {
            $keys[] = self::MEDIUM;
        }

        if (in_array($exercise->type, [ExerciseType::FindError, ExerciseType::Fix], true)) {
            $keys[] = self::DEBUG;
        }

        if (count($this->conceptTags($exercise)) >= 2) {
            $keys[] = self::MIXED;
        }

        return $keys;
    }

    /**
     * Concepts this exercise is tagged with: its concept_id plus any
     * extra tags carried in expected_answer.concept_tags.
     *
     * @return list<int>
     */
    public function conceptTags(Exercise $exercise): array
    {
        $tags = [];

        if ($exercise->concept_id !== null) {
            $tags[] = $exercise->concept_id;
        }

        $extra = $exercise->expected_answer['concept_tags'] ?? [];
        foreach ((array) $extra as $tag) {
            $tags[] = (int) $tag;
        }

        return array_values(array_unique($tags));
    }
}
