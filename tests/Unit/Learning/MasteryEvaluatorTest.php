<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\MasteryLevel;
use App\Enums\ReviewItemType;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Learning\MasteryEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->concept = Concept::factory()->create();
    $this->evaluator = new MasteryEvaluator;
});

it('caps reading-only evidence at learning', function () {
    $mastery = $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::READ);

    expect($mastery->level)->toBe(MasteryLevel::Learning)
        ->and($mastery->evidence)->toHaveKeys([MasteryEvaluator::READ]);
});

it('reaches practicing with an easy exercise', function () {
    $mastery = $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::EASY);

    expect($mastery->level)->toBe(MasteryLevel::Practicing);
});

it('reaches comfortable with easy plus medium plus debug', function () {
    $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::READ);
    $mastery = $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
    );

    expect($mastery->level)->toBe(MasteryLevel::Comfortable);
});

it('reaches mastered only with all six evidence keys', function () {
    $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::READ, MasteryEvaluator::RECALL);
    $mastery = $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
        MasteryEvaluator::MIXED,
    );

    expect($mastery->level)->toBe(MasteryLevel::Mastered)
        ->and($mastery->mastered_at)->not->toBeNull();
});

it('never downgrades when weaker evidence arrives', function () {
    $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::READ,
        MasteryEvaluator::RECALL,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
        MasteryEvaluator::MIXED,
    );

    $mastery = $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::READ);

    expect($mastery->level)->toBe(MasteryLevel::Mastered)
        ->and($mastery->evidence)->toHaveKeys([
            MasteryEvaluator::READ,
            MasteryEvaluator::EASY,
            MasteryEvaluator::MEDIUM,
            MasteryEvaluator::DEBUG,
            MasteryEvaluator::MIXED,
        ]);
});

it('demotes one level and queues a review item', function () {
    $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
    );

    $mastery = $this->evaluator->demote($this->user, $this->concept, 'test_reason');

    expect($mastery->level)->toBe(MasteryLevel::Practicing);

    $item = ReviewItem::query()->sole();
    expect($item->item_type)->toBe(ReviewItemType::Concept)
        ->and($item->item_id)->toBe($this->concept->id)
        ->and($item->reason)->toBe('test_reason');
});

it('does nothing when demoting an unseen concept', function () {
    $result = $this->evaluator->demote($this->user, $this->concept, 'test_reason');

    expect($result)->toBeNull()
        ->and(ReviewItem::query()->count())->toBe(0);
});

it('demotes a mastered concept idle for more than 30 days', function () {
    $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::READ,
        MasteryEvaluator::RECALL,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
        MasteryEvaluator::MIXED,
    );

    $row = $this->user->conceptMastery()->sole();
    $row->forceFill(['mastered_at' => now()->subDays(31)])->save();

    expect($this->evaluator->applyIdleDemotions($this->user))->toBe(1);

    $row->refresh();
    expect($row->level)->toBe(MasteryLevel::Comfortable)
        ->and($row->mastered_at)->toBeNull()
        ->and(ReviewItem::query()->sole()->reason)->toBe(MasteryEvaluator::IDLE_REVIEW_REASON);
});

it('keeps a recently mastered concept untouched', function () {
    $this->evaluator->promote(
        $this->user,
        $this->concept,
        MasteryEvaluator::READ,
        MasteryEvaluator::RECALL,
        MasteryEvaluator::EASY,
        MasteryEvaluator::MEDIUM,
        MasteryEvaluator::DEBUG,
        MasteryEvaluator::MIXED,
    );

    $row = $this->user->conceptMastery()->sole();
    $row->forceFill(['mastered_at' => now()->subDays(10)])->save();

    expect($this->evaluator->applyIdleDemotions($this->user))->toBe(0)
        ->and($row->refresh()->level)->toBe(MasteryLevel::Mastered);
});

it('demotes after three consecutive failures on the same concept', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->concept->id,
        'type' => ExerciseType::Write,
        'difficulty' => 'medium',
    ]);

    $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::EASY);

    foreach (range(1, 3) as $ignored) {
        ExerciseAttempt::query()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'code' => '<?php',
            'result' => AttemptResult::Incorrect,
        ]);

        $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Incorrect);
    }

    expect($this->user->conceptMastery()->sole()->level)->toBe(MasteryLevel::Learning)
        ->and(ReviewItem::query()->sole()->reason)->toBe(MasteryEvaluator::FAILURE_REVIEW_REASON);
});

it('does not demote when a correct attempt breaks the failure streak', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->concept->id,
        'type' => ExerciseType::Write,
        'difficulty' => 'medium',
    ]);

    $this->evaluator->promote($this->user, $this->concept, MasteryEvaluator::EASY);

    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Incorrect);
    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Incorrect);
    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Correct);
    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Incorrect);

    expect($this->user->conceptMastery()->sole()->level)->toBe(MasteryLevel::Practicing)
        ->and(ReviewItem::query()->count())->toBe(0);
});

it('feeds correct attempts into the evidence matrix', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->concept->id,
        'type' => ExerciseType::Write,
        'difficulty' => 'easy',
    ]);

    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Correct);

    $mastery = $this->user->conceptMastery()->sole();
    expect($mastery->level)->toBe(MasteryLevel::Practicing)
        ->and($mastery->evidence)->toHaveKey(MasteryEvaluator::EASY);
});

it('ignores incorrect attempts for evidence but counts them for failures', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->concept->id,
        'type' => ExerciseType::Write,
        'difficulty' => 'easy',
    ]);

    $this->evaluator->recordAttempt($this->user, $exercise, AttemptResult::Incorrect);

    expect($this->user->conceptMastery()->count())->toBe(0);
});

it('awards debug and mixed keys from exercise shape', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->concept->id,
        'type' => ExerciseType::FindError,
        'difficulty' => 'medium',
        'expected_answer' => ['concept_tags' => [$this->concept->id, 999]],
    ]);

    $keys = $this->evaluator->evidenceKeysForExercise($exercise, AttemptResult::Correct);

    expect($keys)->toBe([MasteryEvaluator::MEDIUM, MasteryEvaluator::DEBUG, MasteryEvaluator::MIXED]);
});
