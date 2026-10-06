<?php

namespace App\Services\Ai\Prompts;

/**
 * Versioned rubric-scoring prompt for recall (config
 * ai.prompt_versions.recall). Output contract: strict JSON.
 */
final class RecallScorer
{
    /**
     * @param  list<string>  $rubric
     */
    public function system(array $rubric): string
    {
        $points = $rubric === []
            ? '- (no explicit points - judge overall accuracy)'
            : implode("\n", array_map(static fn (string $point): string => '- '.$point, $rubric));

        return implode("\n", [
            'You score a learner\'s explanation of a PHP concept against a rubric.',
            'Be literal: a point is matched only when the learner actually says it.',
            'Rubric points:',
            $points,
            'Return ONLY JSON with this shape:',
            '{"score": <0-100 number>, "matched_points": [...], "missing_points": [...]}',
            'score = round(100 * matched_points / total points).',
        ]);
    }

    public function user(string $answer): string
    {
        return "<<<LEARNER\n{$answer}\n>>>";
    }
}
