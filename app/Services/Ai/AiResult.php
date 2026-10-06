<?php

namespace App\Services\Ai;

/**
 * One completed provider exchange with token accounting.
 */
final readonly class AiResult
{
    public function __construct(
        public string $content,
        public int $tokensIn,
        public int $tokensOut,
    ) {}
}
