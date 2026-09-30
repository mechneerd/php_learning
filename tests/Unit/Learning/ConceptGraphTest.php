<?php

use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use App\Services\Learning\ConceptGraph;
use App\Services\Learning\CycleDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Builds a chain a --> b --> c where each right-hand concept depends on the left.
 *
 * @return array{a: Concept, b: Concept, c: Concept}
 */
function graph_chain(): array
{
    $a = Concept::factory()->create(['slug' => 'chain-a']);
    $b = Concept::factory()->create(['slug' => 'chain-b']);
    $c = Concept::factory()->create(['slug' => 'chain-c']);

    ConceptPrerequisite::query()->create(['concept_id' => $b->id, 'prereq_concept_id' => $a->id]);
    ConceptPrerequisite::query()->create(['concept_id' => $c->id, 'prereq_concept_id' => $b->id]);

    return ['a' => $a, 'b' => $b, 'c' => $c];
}

it('walks ancestors nearest-first', function () {
    ['a' => $a, 'b' => $b, 'c' => $c] = graph_chain();

    $ancestors = (new ConceptGraph)->ancestors($c);

    expect($ancestors->pluck('slug')->all())->toBe(['chain-b', 'chain-a'])
        ->and($ancestors->pluck('id')->all())->not->toContain($c->id)
        ->and($a->id)->toBeInt();
});

it('walks descendants nearest-first', function () {
    ['a' => $a, 'b' => $b, 'c' => $c] = graph_chain();

    $descendants = (new ConceptGraph)->descendants($a);

    expect($descendants->pluck('slug')->all())->toBe(['chain-b', 'chain-c'])
        ->and($descendants->pluck('id')->all())->not->toContain($a->id)
        ->and($b->id)->toBeInt();
});

it('returns an empty collection for a concept without edges', function () {
    $lonely = Concept::factory()->create();
    $graph = new ConceptGraph;

    expect($graph->ancestors($lonely)->all())->toBe([])
        ->and($graph->descendants($lonely)->all())->toBe([]);
});

it('rejects a self edge', function () {
    $a = Concept::factory()->create();

    expect((new ConceptGraph)->wouldCreateCycle($a->id, $a->id))->toBeTrue();
});

it('rejects an edge that would close a loop', function () {
    ['a' => $a, 'b' => $b, 'c' => $c] = graph_chain();
    $graph = new ConceptGraph;

    // c --> ... --> b --> a already holds, so making a depend on c loops the graph.
    expect($graph->wouldCreateCycle($a->id, $c->id))->toBeTrue();

    $graph->addPrerequisite($a, $c);
})->throws(CycleDetected::class);

it('allows an edge between unrelated concepts', function () {
    ['a' => $a, 'b' => $b] = graph_chain();
    $d = Concept::factory()->create(['slug' => 'chain-d']);
    $graph = new ConceptGraph;

    expect($graph->wouldCreateCycle($d->id, $a->id))->toBeFalse();

    $graph->addPrerequisite($d, $a);

    expect(ConceptPrerequisite::query()
        ->where('concept_id', $d->id)
        ->where('prereq_concept_id', $a->id)
        ->exists())->toBeTrue()
        ->and($graph->ancestors($d)->pluck('slug')->all())->toBe(['chain-a'])
        ->and($graph->descendants($d)->all())->toBe([])
        ->and($graph->descendants($a)->pluck('slug')->sort()->values()->all())
        ->toBe(['chain-b', 'chain-c', 'chain-d'])
        ->and($b->id)->toBeInt();
});

it('treats the same edge twice as an update, not a duplicate', function () {
    ['a' => $a, 'b' => $b] = graph_chain();
    $graph = new ConceptGraph;

    $graph->addPrerequisite($b, $a, 1);
    $graph->addPrerequisite($b, $a, 1);

    expect(ConceptPrerequisite::query()->count())->toBe(2);
});
