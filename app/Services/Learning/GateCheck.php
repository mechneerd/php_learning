<?php

namespace App\Services\Learning;

/**
 * One gate line with its live evaluation, e.g. "inheritance >= practicing".
 */
final readonly class GateCheck
{
    public function __construct(
        public string $label,
        public bool $passed,
        public string $have,
        public string $need,
    ) {}
}
