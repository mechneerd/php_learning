<?php

use App\Enums\ContentStatus;
use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use App\Models\Lesson;
use App\Models\Stage;
use App\Services\Learning\ConceptGraph;
use Database\Seeders\ConceptGraphSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Support\Facades\DB;

it('seeds concepts and knowledge-map edges', function () {
    (new ConceptGraphSeeder)->run();

    expect(Concept::query()->count())->toBeGreaterThanOrEqual(90)
        ->and(ConceptPrerequisite::query()->count())->toBeGreaterThanOrEqual(45)
        ->and(Concept::query()->where('slug', 'class')->exists())->toBeTrue()
        ->and(Concept::query()->where('slug', 'factory')->exists())->toBeTrue()
        ->and(Concept::query()->where('slug', 'strategy')->exists())->toBeTrue();
});

it('stores soft used-later edges with weight zero and hard edges with weight one', function () {
    (new ConceptGraphSeeder)->run();

    $softPrereqs = ConceptPrerequisite::query()->where('weight', 0)->pluck('prereq_concept_id');
    $softSlugs = Concept::query()->whereIn('id', $softPrereqs)->pluck('slug')->sort()->values()->all();
    $class = Concept::query()->where('slug', 'class')->firstOrFail();
    $interface = Concept::query()->where('slug', 'interface')->firstOrFail();

    expect($softSlugs)->toBe(['di', 'enterprise', 'persistence'])
        ->and(ConceptPrerequisite::query()->where('weight', 1)->count())->toBeGreaterThan(3)
        ->and(ConceptPrerequisite::query()
            ->where('weight', 1)
            ->where('concept_id', $interface->id)
            ->where('prereq_concept_id', $class->id)
            ->exists())->toBeTrue();
});

it('creates every concept referenced by a stage gate', function () {
    (new StageSeeder)->run();
    (new ConceptGraphSeeder)->run();

    $missing = [];

    foreach (Stage::query()->whereNotNull('gate_rules')->get() as $stage) {
        foreach ($stage->gate_rules as $rule) {
            if (isset($rule['concept']) && ! Concept::query()->where('slug', $rule['concept'])->exists()) {
                $missing[] = $rule['concept'];
            }
        }
    }

    expect($missing)->toBe([]);
});

it('keeps the graph acyclic and free of self edges', function () {
    (new ConceptGraphSeeder)->run();
    $graph = new ConceptGraph;

    expect(ConceptPrerequisite::query()
        ->whereColumn('concept_id', 'prereq_concept_id')
        ->count())->toBe(0);

    foreach (ConceptPrerequisite::query()->get() as $edge) {
        expect($graph->wouldCreateCycle($edge->concept_id, $edge->prereq_concept_id))->toBeFalse();
    }
});

it('links chapter 3 lessons to their concepts', function () {
    (new StageSeeder)->run();
    $stage = Stage::query()->where('number', 2)->firstOrFail();

    $lesson = Lesson::factory()->create([
        'stage_id' => $stage->id,
        'slug' => 'ch3-classes-and-objects',
        'title' => 'Classes and Objects',
        'status' => ContentStatus::Published,
        'ord' => 1,
    ]);

    (new ConceptGraphSeeder)->run();

    expect($lesson->concepts()->wherePivot('role', 'core')->get()->pluck('slug')->all())
        ->toBe(['class']);
});

it('skips lesson links gracefully when lessons are missing', function () {
    (new ConceptGraphSeeder)->run();

    expect(DB::table('lesson_concepts')->count())->toBe(0);
});

it('is idempotent', function () {
    (new StageSeeder)->run();
    (new ConceptGraphSeeder)->run();

    $before = [
        Concept::query()->count(),
        ConceptPrerequisite::query()->count(),
        DB::table('lesson_concepts')->count(),
    ];

    (new ConceptGraphSeeder)->run();

    expect([
        Concept::query()->count(),
        ConceptPrerequisite::query()->count(),
        DB::table('lesson_concepts')->count(),
    ])->toBe($before);
});
