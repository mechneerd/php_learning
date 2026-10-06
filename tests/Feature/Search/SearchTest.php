<?php

use App\Enums\ContentStatus;
use App\Livewire\Learn\SearchPage;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('search'))->assertRedirect(route('login'));
});

test('shows the empty prompt before a query', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Type to search');
});

test('shows grouped results with highlighted snippets', function () {
    $concept = Concept::factory()->create([
        'name' => 'Polymorphism',
        'definition' => 'One interface, many implementations of polymorphism.',
    ]);
    $this->actingAs(User::factory()->create());

    $this->artisan('search:reindex', ['--entity' => 'concept'])->assertExitCode(0);

    $this->get(route('search', ['q' => 'polymorphism']))
        ->assertOk()
        ->assertSee('Concept')
        ->assertSee('<mark>polymorphism</mark>', false)
        ->assertSee(route('concepts.show', $concept->slug));
});

test('reports no results for an unknown term', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('search', ['q' => 'zzzznotpresent']))
        ->assertOk()
        ->assertSee('No results for');
});

test('reindexes every entity with --all', function () {
    $this->actingAs(User::factory()->create());
    Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->artisan('search:reindex', ['--all' => true])->assertExitCode(0);

    expect(DB::table('search_index')->count())->toBeGreaterThan(0);
});

test('reindexes a single entity', function () {
    $this->actingAs(User::factory()->create());
    Concept::factory()->create();
    Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->artisan('search:reindex', ['--entity' => 'concept'])->assertExitCode(0);

    $types = DB::table('search_index')
        ->select('entity_type')
        ->distinct()
        ->pluck('entity_type')
        ->all();

    expect($types)->toBe(['concept']);
});

test('rejects an unknown entity', function () {
    $this->artisan('search:reindex', ['--entity' => 'bogus'])->assertExitCode(1);
});

test('requires an option', function () {
    $this->artisan('search:reindex')->assertExitCode(1);
});

test('search page accepts the q url parameter', function () {
    Concept::factory()->create(['name' => 'Autoloading', 'definition' => 'PSR-4 maps namespaces to paths for autoloading.']);
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->artisan('search:reindex', ['--entity' => 'concept'])->assertExitCode(0);

    Livewire::actingAs($user)
        ->test(SearchPage::class)
        ->set('q', 'autoloading')
        ->assertSee('Autoloading')
        ->assertSee('<mark>', false);
});
