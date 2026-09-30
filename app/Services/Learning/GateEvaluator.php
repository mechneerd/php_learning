<?php

namespace App\Services\Learning;

use App\Enums\MasteryLevel;
use InvalidArgumentException;

/**
 * Evaluates `stages.gate_rules` (docs/06: [{concept, min_level}] or
 * [{quiz, min_score}], plus [{exercises,n}], [{comfortable_pct,n}],
 * [{mastered_pct,n}] from 05-knowledge-map.md section 2).
 *
 * Pure: evidence is supplied by the caller so the evaluator never touches
 * tables that later phases own.
 */
final class GateEvaluator
{
    /**
     * @param  array<array<string, mixed>>|null  $rules
     */
    public function evaluate(?array $rules, GateEvidence $evidence): GateResult
    {
        if ($rules === null || $rules === []) {
            return new GateResult([]);
        }

        $checks = [];

        foreach ($rules as $rule) {
            $checks[] = $this->check($rule, $evidence);
        }

        return new GateResult($checks);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function check(array $rule, GateEvidence $evidence): GateCheck
    {
        if (isset($rule['concept'])) {
            $slug = (string) $rule['concept'];
            $minimum = MasteryLevel::tryFrom((string) ($rule['min_level'] ?? ''))
                ?? MasteryLevel::Practicing;
            $level = $evidence->levelFor($slug);

            return new GateCheck(
                label: "{$slug} >= {$minimum->value}",
                passed: $level->atLeast($minimum),
                have: $level->value,
                need: $minimum->value,
            );
        }

        if (isset($rule['quiz'])) {
            $quiz = (string) $rule['quiz'];
            $need = (int) ($rule['min_score'] ?? 100);
            $have = $evidence->scoreFor($quiz);

            return new GateCheck(
                label: "quiz {$quiz} >= {$need}%",
                passed: $have >= $need,
                have: "{$have}%",
                need: "{$need}%",
            );
        }

        if (isset($rule['exercises'])) {
            $need = (int) $rule['exercises'];
            $have = $evidence->correctExercises;

            return new GateCheck(
                label: "{$need} exercises correct",
                passed: $have >= $need,
                have: (string) $have,
                need: (string) $need,
            );
        }

        if (isset($rule['comfortable_pct'])) {
            $need = (int) $rule['comfortable_pct'];
            $have = $evidence->comfortablePct;

            return new GateCheck(
                label: "{$need}% of concepts comfortable",
                passed: $have >= $need,
                have: "{$have}%",
                need: "{$need}%",
            );
        }

        if (isset($rule['mastered_pct'])) {
            $need = (int) $rule['mastered_pct'];
            $have = $evidence->masteredPct;

            return new GateCheck(
                label: "{$need}% of concepts mastered",
                passed: $have >= $need,
                have: "{$have}%",
                need: "{$need}%",
            );
        }

        throw new InvalidArgumentException('Unsupported gate rule: '.json_encode($rule));
    }
}
