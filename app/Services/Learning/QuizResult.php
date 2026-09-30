<?php

namespace App\Services\Learning;

final readonly class QuizResult
{
    /**
     * @param  list<string>  $weakConcepts  concept slugs scored below 60%
     */
    public function __construct(
        public float $score,
        public int $correct,
        public int $total,
        public array $weakConcepts,
    ) {}
}
