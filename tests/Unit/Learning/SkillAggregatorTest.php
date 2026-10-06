<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\MasteryLevel;
use App\Enums\SkillDomain;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\SkillProgress;
use App\Models\User;
use App\Services\Learning\SkillAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->aggregator = new SkillAggregator;
});

it('returns all sixteen skill domains', function () {
    $rows = $this->aggregator->aggregate($this->user);

    expect($rows)->toHaveCount(16)
        ->and(array_column($rows, 'domain'))->toBe(SkillDomain::cases());
});

it('reports unseen domains when the learner has no evidence', function () {
    Concept::factory()->create(['skill_domain' => SkillDomain::Oop->value]);

    $rows = collect($this->aggregator->aggregate($this->user))->keyBy(fn (array $row): string => $row['domain']->value);

    expect($rows[SkillDomain::Oop->value]['level'])->toBe(MasteryLevel::Unseen)
        ->and($rows[SkillDomain::Oop->value]['concept_count'])->toBe(1)
        ->and($rows[SkillDomain::Oop->value]['pass_rate'])->toBeNull();
});

it('marks a domain mastered when concepts are mastered and the pass rate is high', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $concepts = Concept::factory()->count(2)->create(['skill_domain' => SkillDomain::Oop->value]);

    foreach ($concepts as $concept) {
        ConceptMastery::factory()->mastered()->create([
            'user_id' => $this->user->id,
            'concept_id' => $concept->id,
        ]);
    }

    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $concepts->first()->id,
    ]);

    ExerciseAttempt::query()->create([
        'user_id' => $this->user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 1;',
        'result' => AttemptResult::Correct,
    ]);

    $rows = collect($this->aggregator->aggregate($this->user))->keyBy(fn (array $row): string => $row['domain']->value);

    expect($rows[SkillDomain::Oop->value]['level'])->toBe(MasteryLevel::Mastered)
        ->and($rows[SkillDomain::Oop->value]['mastered_count'])->toBe(2)
        ->and($rows[SkillDomain::Oop->value]['pass_rate'])->toBe(1.0);
});

it('caps the domain at comfortable when the pass rate is low', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $concepts = Concept::factory()->count(2)->create(['skill_domain' => SkillDomain::Oop->value]);

    foreach ($concepts as $concept) {
        ConceptMastery::factory()->mastered()->create([
            'user_id' => $this->user->id,
            'concept_id' => $concept->id,
        ]);
    }

    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $concepts->first()->id,
    ]);

    ExerciseAttempt::query()->create([
        'user_id' => $this->user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 1;',
        'result' => AttemptResult::Correct,
    ]);
    ExerciseAttempt::query()->create([
        'user_id' => $this->user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 2;',
        'result' => AttemptResult::Incorrect,
    ]);

    $rows = collect($this->aggregator->aggregate($this->user))->keyBy(fn (array $row): string => $row['domain']->value);

    expect($rows[SkillDomain::Oop->value]['level'])->toBe(MasteryLevel::Comfortable)
        ->and($rows[SkillDomain::Oop->value]['pass_rate'])->toBe(0.5);
});

it('persists one skill_progress row per domain', function () {
    $this->aggregator->aggregate($this->user);

    expect(SkillProgress::query()->where('user_id', $this->user->id)->count())->toBe(16);
});
