<?php

use App\Enums\ContentStatus;
use App\Enums\MasteryLevel;
use App\Enums\ProjectLevel;
use App\Livewire\Learn\ProjectDetail;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->guard()->logout();

    $this->get(route('projects'))->assertRedirect(route('login'));
});

test('lists published projects grouped by level with lock state', function () {
    Concept::factory()->create(['slug' => 'composition', 'name' => 'Composition']);
    $locked = Project::factory()->create(['title' => 'CLI Stats', 'level' => ProjectLevel::Beginner, 'ord' => 1]);
    ProjectTask::factory()->create([
        'project_id' => $locked->id,
        'ord' => 1,
        'concept_ids' => ['composition'],
    ]);
    Project::factory()->create(['title' => 'Issue Tracker', 'level' => ProjectLevel::Capstone, 'ord' => 9]);

    $this->get(route('projects'))
        ->assertOk()
        ->assertSee('Projects')
        ->assertSee('CLI Stats')
        ->assertSee('Issue Tracker')
        ->assertSee('Beginner')
        ->assertSee('Capstone')
        ->assertSee('concepts not comfortable yet')
        ->assertSee('Unlocked');
});

test('project detail shows a lock notice until its concepts are comfortable', function () {
    Concept::factory()->create(['slug' => 'composition', 'name' => 'Composition']);
    $project = Project::factory()->create(['title' => 'Library Catalog']);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'ord' => 1,
        'brief' => 'Model books as readonly value objects.',
        'concept_ids' => ['composition'],
    ]);

    $this->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertSee('This project is locked')
        ->assertSee('Composition')
        ->assertSee('Tasks appear once the concepts above are Comfortable.');

    ConceptMastery::factory()->create([
        'user_id' => $this->user->id,
        'concept_id' => Concept::query()->where('slug', 'composition')->value('id'),
        'level' => MasteryLevel::Comfortable,
    ]);

    $this->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertDontSee('This project is locked')
        ->assertSee('Model books as readonly value objects.');
});

test('one uncomfortable concept keeps the project locked', function () {
    Concept::factory()->create(['slug' => 'composition']);
    Concept::factory()->create(['slug' => 'encapsulation']);
    $project = Project::factory()->create();
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'ord' => 1,
        'concept_ids' => ['composition', 'encapsulation'],
    ]);

    ConceptMastery::factory()->create([
        'user_id' => $this->user->id,
        'concept_id' => Concept::query()->where('slug', 'composition')->value('id'),
        'level' => MasteryLevel::Mastered,
    ]);

    $this->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertSee('This project is locked');
});

test('solution stays gated until the first task is checked', function () {
    $project = Project::factory()->create([
        'solution_ref' => 'Extract a Plan interface and inject it into the dispatcher.',
    ]);
    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'ord' => 1,
        'brief' => 'Write the failing test first.',
        'concept_ids' => [],
    ]);

    $this->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertSee('Available after 1 attempt')
        ->assertDontSee('Extract a Plan interface');

    $component = Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('toggleTask', $task->id)
        ->assertDontSee('Available after 1 attempt')
        ->assertSee('Extract a Plan interface');

    expect(Project::query()->find($project->id)->progressFor($this->user))
        ->toBe(['checked' => 1, 'total' => 1]);

    $component->call('toggleTask', $task->id)
        ->assertSee('Available after 1 attempt');

    expect(Project::query()->find($project->id)->progressFor($this->user)['checked'])->toBe(0);
});

test('draft projects return 404 on the detail page', function () {
    $project = Project::factory()->create(['status' => ContentStatus::Draft]);

    $this->get(route('projects.show', $project->slug))->assertNotFound();
});
