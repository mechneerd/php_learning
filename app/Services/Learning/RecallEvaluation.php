<?php

namespace App\Services\Learning;

/**
 * Result of scoring one explain-it answer against a rubric.
 */
final readonly class RecallEvaluation
{
    public const PASS_SCORE = 70.0;

    /**
     * @param  list<string>  $matchedPoints
     * @param  list<string>  $missingPoints
     */
    public function __construct(
        public float $score,
        public array $matchedPoints,
        public array $missingPoints,
    ) {}

    public function passed(): bool
    {
        return $this->score >= self::PASS_SCORE;
    }
}
