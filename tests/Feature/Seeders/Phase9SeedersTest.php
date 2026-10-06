<?php

use App\Enums\ContentStatus;
use App\Enums\ErrorCategory;
use App\Enums\ProjectLevel;
use App\Models\ErrorPattern;
use App\Models\InterviewQuestion;
use App\Models\Project;
use Database\Seeders\ConceptGraphSeeder;
use Database\Seeders\ErrorPatternSeeder;
use Database\Seeders\InterviewQuestionSeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\StageSeeder;

test('project seeder builds the full ladder idempotently', function () {
    (new StageSeeder)->run();
    (new ProjectSeeder)->run();
    (new ProjectSeeder)->run();

    expect(Project::query()->published()->count())->toBe(13)
        ->and(Project::query()->where('level', 'beginner')->count())->toBe(4)
        ->and(Project::query()->where('level', 'intermediate')->count())->toBe(4)
        ->and(Project::query()->where('level', 'advanced')->count())->toBe(4)
        ->and(Project::query()->where('level', 'capstone')->count())->toBe(1);

    foreach (Project::query()->published()->get() as $project) {
        expect($project->tasks()->count())->toBeGreaterThanOrEqual(4)
            ->and($project->requirements)->not->toBeEmpty()
            ->and($project->solution_ref)->not->toBeNull()
            ->and($project->brief)->not->toBeEmpty();
    }

    expect(Project::query()->where('slug', 'issue-tracker')->first()?->level)->toBe(ProjectLevel::Capstone);
});

test('interview seeder publishes at least 80 questions across topics', function () {
    (new ConceptGraphSeeder)->run();
    (new InterviewQuestionSeeder)->run();
    (new InterviewQuestionSeeder)->run();

    expect(InterviewQuestion::query()->published()->count())->toBeGreaterThanOrEqual(80)
        ->and(InterviewQuestion::query()->where('status', ContentStatus::Draft)->count())->toBe(0)
        ->and(InterviewQuestion::query()->distinct()->pluck('topic')->all())
        ->toEqualCanonicalizing(['oop', 'core', 'patterns', 'architecture', 'testing', 'database']);

    expect(InterviewQuestion::query()->distinct()->count('question'))->toBe(InterviewQuestion::query()->count());

    $withFollowUps = InterviewQuestion::query()->whereNotNull('follow_ups')->count();
    expect($withFollowUps)->toBe(InterviewQuestion::query()->count());

    expect(InterviewQuestion::query()->whereNotNull('concept_id')->count())->toBeGreaterThan(0);
});

test('error pattern seeder publishes ten patterns covering every category', function () {
    (new ErrorPatternSeeder)->run();
    (new ErrorPatternSeeder)->run();

    $categories = ErrorPattern::query()->published()->pluck('category')
        ->map(fn ($category): string => $category instanceof ErrorCategory ? $category->value : (string) $category)
        ->unique()
        ->values()
        ->all();

    expect(ErrorPattern::query()->published()->count())->toBe(10)
        ->and($categories)
        ->toEqualCanonicalizing(['parse', 'type', 'undefined', 'method', 'fatal', 'exception', 'runtime']);

    foreach (ErrorPattern::query()->get() as $pattern) {
        expect($pattern->identify_steps)->not->toBeEmpty()
            ->and($pattern->fix_steps)->not->toBeEmpty()
            ->and($pattern->prevent_steps)->not->toBeEmpty()
            ->and($pattern->symptom)->not->toBeEmpty()
            ->and($pattern->cause)->not->toBeEmpty();
    }
});
