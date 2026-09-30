<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\ExerciseTest;
use Illuminate\Support\Collection;

/**
 * Static exercise grader. Pre-Phase 8 nothing is executed in-process:
 * code answers are checked with static rules (contain / regex / banned
 * functions), stored-answer types are compared against expected answers.
 */
final class ExerciseGrader
{
    public const PENDING_MESSAGE = 'Live execution arrives in Phase 8 — answers are checked with static rules for now.';

    /**
     * Functions that must never appear in a submitted answer (docs/12).
     *
     * @var list<string>
     */
    private const BANNED = [
        'eval', 'assert', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'curl_exec',
        'fsockopen', 'dl', 'putenv', 'mail',
    ];

    /**
     * @param  Collection<int, ExerciseTest>  $tests
     */
    public function grade(Exercise $exercise, string $answer, Collection $tests): GradeResult
    {
        if ($exercise->type->usesStoredAnswer()) {
            return $this->gradeStoredAnswer($exercise, $answer);
        }

        $banned = $this->bannedFunctionHit($answer);
        if ($banned !== null) {
            return new GradeResult(
                AttemptResult::Error,
                [],
                "Blocked: `{$banned}` is not allowed in submissions (execution safety rules).",
            );
        }

        return $this->gradeByTests($answer, $tests);
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
                'No keyword rubric is configured for this exercise yet — compare your answer with the explanation.',
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
    private function gradeByTests(string $code, Collection $tests): GradeResult
    {
        $results = [];
        $scorablePass = 0;
        $scorableTotal = 0;

        foreach ($tests as $test) {
            $result = $this->evaluateTest($test, $code);
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
                ? 'No checks are configured for this exercise yet — '.self::PENDING_MESSAGE
                : self::PENDING_MESSAGE;

            return new GradeResult(AttemptResult::Partial, $results, $feedback);
        }

        if ($scorablePass === $scorableTotal) {
            $result = AttemptResult::Correct;
            $feedback = "All {$scorableTotal} static check(s) passed.";
        } elseif ($scorablePass === 0) {
            $result = AttemptResult::Incorrect;
            $feedback = "None of the {$scorableTotal} static check(s) passed yet.";
        } else {
            $result = AttemptResult::Partial;
            $feedback = "{$scorablePass} of {$scorableTotal} static checks passed.";
        }

        return new GradeResult($result, $results, $feedback);
    }

    private function evaluateTest(ExerciseTest $test, string $code): TestResult
    {
        $payload = $test->payload ?? [];

        return match ($test->type) {
            'assert_contains' => $this->codeContains($test, (string) ($payload['needle'] ?? ''), $code),
            'assert_regex' => $this->codeRegex($test, (string) ($payload['pattern'] ?? ''), $code),
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

    /**
     * First banned function found in the submission, or null.
     */
    private function bannedFunctionHit(string $code): ?string
    {
        foreach (self::BANNED as $fn) {
            if (preg_match('/\b'.preg_quote($fn, '/').'\s*\(/i', $code) === 1) {
                return $fn;
            }
        }

        if (preg_match('/file_get_contents\s*\(\s*[\'"]https?:/i', $code) === 1) {
            return 'file_get_contents(http…)';
        }

        if (preg_match('/\b(include|require)(_once)?\s*\(\s*[\'"]https?:/i', $code) === 1) {
            return 'remote include';
        }

        return null;
    }
}
