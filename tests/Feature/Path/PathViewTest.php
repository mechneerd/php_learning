<?php

use App\Enums\ContentStatus;
use App\Livewire\Learn\PathView;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Stage;
use App\Models\User;
use Database\Seeders\ConceptGraphSeeder;
use Database\Seeders\StageSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new StageSeeder)->run();
    (new ConceptGraphSeeder)->run();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the stage rail, graph and gate panel', function () {
    $this->get('/path')
        ->assertOk()
        ->assertSee('Learning Path')
        ->assertSee('Stage 0')
        ->assertSee('No lessons yet')
        ->assertSee('flowchart TD')
        ->assertSee('Classes & Objects')
        ->assertSee('Enter Stage 2')
        ->assertSee('quiz php-foundations >= 80%')
        ->assertSee('fail');
});

it('draws soft knowledge-map edges as dashed arrows', function () {
    $this->get('/path')
        ->assertOk()
        ->assertSee('-.->');
});

it('recommends the first unread lesson in stage order', function () {
    $stage = Stage::query()->where('number', 2)->firstOrFail();

    $alpha = Lesson::factory()->create([
        'stage_id' => $stage->id,
        'title' => 'Alpha Lesson',
        'slug' => 'alpha-lesson',
        'status' => ContentStatus::Published,
        'ord' => 1,
    ]);
    Lesson::factory()->create([
        'stage_id' => $stage->id,
        'title' => 'Beta Lesson',
        'slug' => 'beta-lesson',
        'status' => ContentStatus::Published,
        'ord' => 2,
    ]);

    $this->get('/path')
        ->assertOk()
        ->assertSee('Next up')
        ->assertSee('Alpha Lesson')
        ->assertDontSee('Beta Lesson');

    LessonProgress::create([
        'user_id' => $this->user->id,
        'lesson_id' => $alpha->id,
        'state' => 'read',
        'active_seconds' => 120,
        'opened_at' => now(),
        'last_at' => now(),
    ]);

    $this->get('/path')
        ->assertOk()
        ->assertSee('Beta Lesson')
        ->assertDontSee('Alpha Lesson');
});

it('serves the concept page from the path component', function () {
    $this->get(route('concepts.show', 'class'))
        ->assertOk()
        ->assertSee('Classes & Objects')
        ->assertSee('Learn this first')
        ->assertSee('Functions')
        ->assertSee('Used later in');
});

it('404s on an unknown concept slug', function () {
    $this->get('/concepts/no-such-concept')->assertNotFound();
});

it('selects and clears a concept through the component', function () {
    Livewire::test(PathView::class)
        ->call('select', 'class')
        ->assertSet('selected', 'class')
        ->assertSee('Learn this first')
        ->call('select', 'class')
        ->assertSet('selected', null)
        ->assertDontSee('Learn this first');
});

it('ignores a selection for a concept that does not exist', function () {
    Livewire::test(PathView::class)
        ->call('select', 'ghost-concept')
        ->assertSet('selected', null);
});

it('marks recommended lesson concepts with a dashed ring in the graph', function () {
    $stage = Stage::query()->where('number', 2)->firstOrFail();
    Lesson::factory()->create([
        'stage_id' => $stage->id,
        'slug' => 'ch3-classes-and-objects',
        'title' => 'Classes and Objects',
        'status' => ContentStatus::Published,
        'ord' => 1,
    ]);

    // Re-run so the lesson_concepts link for this lesson exists.
    (new ConceptGraphSeeder)->run();

    $class = Concept::query()->where('slug', 'class')->firstOrFail();
    $recommendation = Lesson::query()->where('slug', 'ch3-classes-and-objects')->firstOrFail();
    $recommendation->load('concepts');

    $coreIds = $recommendation->concepts->pluck('id')->all();
    expect($coreIds)->toContain($class->id);

    $html = $this->get('/path')->assertOk()->getContent();
    $nodeId = 'n'.$class->id;

    expect($html)->toContain($nodeId.'[&quot;')
        ->and($html)->toContain('stroke-dasharray: 5 5');
});
