<?php

use App\Enums\AttemptResult;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\ExerciseTest;
use App\Services\Learning\ExerciseGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeGraderExercise(array $attributes = [], array $tests = []): Exercise
{
    $exercise = Exercise::factory()->create($attributes);

    foreach ($tests as $index => [$type, $payload, $weight]) {
        ExerciseTest::query()->create([
            'exercise_id' => $exercise->id,
            'ord' => $index + 1,
            'type' => $type,
            'payload' => $payload,
            'weight' => $weight ?? 1,
        ]);
    }

    return $exercise->load('tests');
}

it('passes when every static check matches the submission', function () {
    $exercise = makeGraderExercise(
        ['type' => ExerciseType::Write],
        [
            ['assert_contains', ['needle' => 'class Greeter'], 2],
            ['assert_regex', ['pattern' => 'function\s+greeting'], 1],
        ],
    );

    $result = (new ExerciseGrader)->grade($exercise, "<?php\nclass Greeter { function greeting() {} }", $exercise->tests);

    expect($result->result)->toBe(AttemptResult::Correct)
        ->and($result->tests[0]->status)->toBe('pass')
        ->and($result->tests[1]->status)->toBe('pass');
});

it('fails when a static check does not match', function () {
    $exercise = makeGraderExercise(
        ['type' => ExerciseType::Write],
        [['assert_contains', ['needle' => 'class Greeter extends Base'], 1]],
    );

    $result = (new ExerciseGrader)->grade($exercise, '<?php class Greeter2 {}', $exercise->tests);

    expect($result->result)->toBe(AttemptResult::Incorrect)
        ->and($result->tests[0]->status)->toBe('fail');
});

it('scores partial credit when only some checks pass', function () {
    $exercise = makeGraderExercise(
        ['type' => ExerciseType::Write],
        [
            ['assert_contains', ['needle' => 'class Greeter'], 1],
            ['assert_contains', ['needle' => 'function greeting'], 1],
        ],
    );

    $result = (new ExerciseGrader)->grade($exercise, '<?php class Greeter {}', $exercise->tests);

    expect($result->result)->toBe(AttemptResult::Partial)
        ->and($result->tests[0]->status)->toBe('pass')
        ->and($result->tests[1]->status)->toBe('fail');
});

it('marks execution-dependent checks as pending until the code is run', function () {
    $exercise = makeGraderExercise(
        ['type' => ExerciseType::Write],
        [['assert_output', ['expected' => 'Hello'], 1]],
    );

    $result = (new ExerciseGrader)->grade($exercise, '<?php echo "Hello";', $exercise->tests);

    expect($result->result)->toBe(AttemptResult::Partial)
        ->and($result->tests[0]->status)->toBe('pending')
        ->and($result->feedback)->toContain('press Run');
});

it('grades assert_output against a live run stdout', function () {
    $exercise = makeGraderExercise(
        ['type' => ExerciseType::Write],
        [['assert_output', ['expected' => 'Hello'], 1]],
    );

    $grader = new ExerciseGrader;
    $pass = $grader->grade($exercise, '<?php echo "Hello";', $exercise->tests, "Hello\n");
    $fail = $grader->grade($exercise, '<?php echo "Hello";', $exercise->tests, 'Goodbye');

    expect($pass->result)->toBe(AttemptResult::Correct)
        ->and($pass->tests[0]->status)->toBe('pass')
        ->and($fail->result)->toBe(AttemptResult::Incorrect)
        ->and($fail->tests[0]->status)->toBe('fail');
});

it('blocks banned functions as an error', function () {
    $exercise = makeGraderExercise(['type' => ExerciseType::Write], []);

    $result = (new ExerciseGrader)->grade($exercise, '<?php eval($userInput);', $exercise->tests);

    expect($result->result)->toBe(AttemptResult::Error)
        ->and($result->feedback)->toContain('eval');
});

it('compares stored answers case-insensitively for mcq exercises', function () {
    $exercise = makeGraderExercise([
        'type' => ExerciseType::Mcq,
        'expected_answer' => ['answer' => 'B'],
    ]);

    $grader = new ExerciseGrader;

    expect($grader->grade($exercise, 'b', collect())->result)->toBe(AttemptResult::Correct)
        ->and($grader->grade($exercise, 'A', collect())->result)->toBe(AttemptResult::Incorrect);
});

it('grades explain answers against the keyword rubric', function () {
    $exercise = makeGraderExercise([
        'type' => ExerciseType::Explain,
        'expected_answer' => ['keywords' => ['class', 'object', 'new']],
    ]);

    $grader = new ExerciseGrader;

    $full = $grader->grade($exercise, 'A class is a blueprint; an object is created with new.', collect());
    $partial = $grader->grade($exercise, 'It has to do with classes somehow.', collect());

    expect($full->result)->toBe(AttemptResult::Correct)
        ->and($partial->result)->toBe(AttemptResult::Partial);
});

it('gives partial credit when some explain keywords match', function () {
    $exercise = makeGraderExercise([
        'type' => ExerciseType::Explain,
        'expected_answer' => ['keywords' => ['class', 'object', 'new', 'instance', 'blueprint']],
    ]);

    $result = (new ExerciseGrader)->grade($exercise, 'A class is a blueprint.', collect());

    expect($result->result)->toBe(AttemptResult::Partial);
});
