<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\ExerciseTest;
use App\Services\Execution\BannedFunctionCheck;
use Illuminate\Support\Collection;

/**
 * Exercise grader. Code answers are checked with static rules
 * (contain / regex / banned functions) and, when a live sandbox run for
 * the same source exists, assert_output checks against its stdout.
 * Stored-answer types are compared against expected answers.
 */
final class ExerciseGrader
{
    public const PENDING_MESSAGE = 'Some checks need a live run â€” press Run to execute your code in the sandbox.';

    /**
     * @param  Collection<int, ExerciseTest>  $tests
     */
    public function grade(Exercise $exercise, string $answer, Collection $tests, ?string $liveOutput = null): GradeResult
    {
        if ($exercise->type->usesStoredAnswer()) {
            return $this->gradeStoredAnswer($exercise, $answer);
        }

        $banned = (new BannedFunctionCheck)->firstHit($answer);
        if ($banned !== null) {
            return new GradeResult(
                AttemptResult::Error,
                [],
                "Blocked: `{$banned}` is not allowed in submissions (execution safety rules).",
            );
        }

        return $this->gradeByTests($exercise, $answer, $tests, $liveOutput);
    }

    private function gradeStoredAnswer(Exercise $exercise, string $answer): GradeResult
    {
        $expected = $exercise->expected_answer ?? [];

        if ($exercise->type === ExerciseType::Explain) {
            return $this->gradeExplain($expected, $answer);
        }

        $wanted = trim((string) ($expected['answer'] ?? ''));
        $given = trim($answer);

        $correct = $wanted !== '' && strcasecmp($wanted, $given) === 0;

        return new GradeResult(
            $correct ? AttemptResult::Correct : AttemptResult::Incorrect,
            [],
            $correct
                ? 'Matches the expected answer.'
                : ($wanted === ''
                    ? 'No expected answer is configured for this exercise yet.'
                    : 'Your answer does not match the expected answer yet. Compare it with the explanation and try again.'),
        );
    }

    /**
     * Keyword rubric: correct at >= 60% of keywords matched (docs/04),
     * partial when some match.
     *
     * @param  array<string, mixed>  $expected
     */
    private function gradeExplain(array $expected, string $answer): GradeResult
    {
        /** @var list<string> $keywords */
        $keywords = array_values(array_filter(array_map(
            fn ($k): string => trim((string) $k),
            $expected['keywords'] ?? [],
        ), fn (string $k): bool => $k !== ''));

        if ($keywords === []) {
            return new GradeResult(
                AttemptResult::Partial,
                [],
                'No keyword rubric is configured for this exercise yet â€” compare your answer with the explanation.',
            );
        }

        $lower = mb_strtolower($answer);
        $matched = array_values(array_filter(
            $keywords,
            fn (string $k): bool => str_contains($lower, mb_strtolower($k)),
        ));

        $ratio = count($matched) / count($keywords);

        if ($ratio >= 0.6) {
            $result = AttemptResult::Correct;
            $feedback = count($matched) === count($keywords)
                ? 'All key points covered.'
                : 'Covers most key points: '.implode(', ', $matched).'.';
        } elseif (count($matched) > 0) {
            $result = AttemptResult::Partial;
            $feedback = 'Touches on '.implode(', ', $matched).' but misses key points.';
        } else {
            $result = AttemptResult::Incorrect;
            $feedback = 'None of the expected key points were found in your answer.';
        }

        return new GradeResult($result, [], $feedback);
    }

    /**
     * @param  Collection<int, ExerciseTest>  $tests
     */
    private function gradeByTests(Exercise $exercise, string $code, Collection $tests, ?string $liveOutput): GradeResult
    {
        $results = [];
        $scorablePass = 0;
        $scorableTotal = 0;

        foreach ($tests as $test) {
            $result = $this->evaluateTest($test, $code, $liveOutput);
            $results[] = $result;

            if ($result->status !== 'pending') {
                $scorableTotal++;
                if ($result->status === 'pass') {
                    $scorablePass++;
                }
            }
        }

        if ($scorableTotal === 0) {
            $feedback = $results === []
                ? 'No checks are configured for this exercise yet â€” '.self::PENDING_MESSAGE
                : self::PENDING_MESSAGE;

            return new GradeResult(AttemptResult::Partial, $results, $feedback);
        }

        if ($scorablePass === $scorableTotal) {
            $result = AttemptResult::Correct;
            $feedback = "All {$scorableTotal} check(s) passed.";
        } elseif ($scorablePass === 0) {
            $result = AttemptResult::Incorrect;
            $feedback = "None of the {$scorableTotal} check(s) passed yet.";
        } else {
            $result = AttemptResult::Partial;
            $feedback = "{$scorablePass} of {$scorableTotal} checks passed.";
        }

        return new GradeResult($result, $results, $feedback);
    }

    private function evaluateTest(ExerciseTest $test, string $code, ?string $liveOutput): TestResult
    {
        $payload = $test->payload ?? [];

        return match ($test->type) {
            'assert_contains' => $this->codeContains($test, (string) ($payload['needle'] ?? ''), $code),
            'assert_regex' => $this->codeRegex($test, (string) ($payload['pattern'] ?? ''), $code),
            'assert_output' => $this->outputCheck($test, $liveOutput),
            'static_check' => $this->codeStatic($test, $code),
            default => new TestResult(
                $test->ord,
                $test->type,
                'pending',
                self::PENDING_MESSAGE,
                $test->weight,
            ),
        };
    }

    private function codeContains(ExerciseTest $test, string $needle, string $code): TestResult
    {
        if ($needle === '') {
            return new TestResult($test->ord, $test->type, 'pending', 'Check has no needle configured.', $test->weight);
        }

        $pass = str_contains($code, $needle);

        return new TestResult(
            $test->ord,
            $test->type,
            $pass ? 'pass' : 'fail',
            $pass ? "Found `{$needle}`." : "Missing `{$needle}`.",
            $test->weight,
        );
    }

    private function codeRegex(ExerciseTest $test, string $pattern, string $code): TestResult
    {
        if ($pattern === '') {
            return new TestResult($test->ord, $test->type, 'pending', 'Check has no pattern configured.', $test->weight);
        }

        $regex = $this->withDelimiters($pattern);
        $pass = @preg_match($regex, $code) === 1;

        return new TestResult(
            $test->ord,
            $test->type,
            $pass ? 'pass' : 'fail',
            $pass ? 'Pattern matched.' : "Pattern {$regex} did not match your answer.",
            $test->weight,
        );
    }

    /**
     * Compares the live run's stdout with the expected output; without a
     * finished run the check stays pending.
     */
    private function outputCheck(ExerciseTest $test, ?string $liveOutput): TestResult
    {
        $payload = $test->payload ?? [];
        $expected = $payload['expected'] ?? null;
        $expected = is_string($expected) ? $this->normalizeOutput($expected) : '';

        if ($expected === '') {
            return new TestResult($test->ord, $test->type, 'pending', 'No expected output is configured for this check.', $test->weight);
        }

        if ($liveOutput === null) {
            return new TestResult($test->ord, $test->type, 'pending', 'Run your code to check its output.', $test->weight);
        }

        $pass = $this->normalizeOutput($liveOutput) === $expected;

        return new TestResult(
            $test->ord,
            $test->type,
            $pass ? 'pass' : 'fail',
            $pass ? 'Output matches the expected output.' : 'Output differs from the expected output.',
            $test->weight,
        );
    }

    private function normalizeOutput(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = array_map(fn (string $line): string => rtrim($line), explode("\n", $value));

        return trim(implode("\n", $lines), "\n");
    }

    private function codeStatic(ExerciseTest $test, string $code): TestResult
    {
        $payload = $test->payload ?? [];

        foreach (($payload['banned'] ?? []) as $pattern) {
            $regex = $this->withDelimiters((string) $pattern);
            if (@preg_match($regex, $code) === 1) {
                return new TestResult(
                    $test->ord,
                    $test->type,
                    'fail',
                    "Must not use: {$pattern}.",
                    $test->weight,
                );
            }
        }

        foreach (($payload['required'] ?? []) as $pattern) {
            $regex = $this->withDelimiters((string) $pattern);
            if (@preg_match($regex, $code) !== 1) {
                return new TestResult(
                    $test->ord,
                    $test->type,
                    'fail',
                    "Missing required pattern: {$pattern}.",
                    $test->weight,
                );
            }
        }

        if (($payload['banned'] ?? []) === [] && ($payload['required'] ?? []) === []) {
            return new TestResult($test->ord, $test->type, 'pending', 'Check has no rules configured.', $test->weight);
        }

        return new TestResult($test->ord, $test->type, 'pass', 'Static rules satisfied.', $test->weight);
    }

    private function withDelimiters(string $pattern): string
    {
        if ($pattern !== '' && $pattern[0] === '/' && str_ends_with($pattern, '/')) {
            return $pattern;
        }

        return '/'.str_replace('/', '\/', $pattern).'/i';
    }
}
