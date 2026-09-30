<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;

final readonly class GradeResult
{
    /**
     * @param  list<TestResult>  $tests
     */
    public function __construct(
        public AttemptResult $result,
        public array $tests,
        public string $feedback,
    ) {}

    /** @return list<array{ord: int, type: string, status: string, message: string, weight: int}> */
    public function testResultsArray(): array
    {
        return array_map(fn (TestResult $t): array => $t->toArray(), $this->tests);
    }
}
