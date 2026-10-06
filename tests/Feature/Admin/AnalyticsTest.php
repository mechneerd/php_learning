<?php

use App\Enums\ContentStatus;
use App\Models\AiGeneration;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;

it('blocks guests from analytics', function () {
    $this->get('/admin/analytics')->assertRedirect('/login');
});

it('blocks learners from analytics', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/analytics')
        ->assertForbidden();
});

it('allows admins into analytics', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/analytics')
        ->assertOk()
        ->assertSee('Coverage per stage')
        ->assertSee('Exercise pass rates');
});

it('shows stage coverage with published lesson counts', function () {
    $stage = Stage::factory()->create(['name' => 'OOP Fundamentals']);
    Lesson::factory()->create(['stage_id' => $stage->id, 'status' => ContentStatus::Published]);
    Lesson::factory()->create(['stage_id' => $stage->id, 'status' => ContentStatus::Draft]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/analytics')
        ->assertOk()
        ->assertSee('OOP Fundamentals')
        ->assertSee('1 / 2');
});

it('shows exercise pass rates from attempts', function () {
    $exercise = Exercise::factory()->create();
    ExerciseAttempt::factory()->count(2)->create(['exercise_id' => $exercise->id]);
    ExerciseAttempt::factory()->incorrect()->create(['exercise_id' => $exercise->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/analytics')
        ->assertOk()
        ->assertSee('67%');
});

it('shows ai spend totals and the 30 day chart', function () {
    AiGeneration::factory()->create(['cost' => 0.0100, 'created_at' => now()->subDays(2)]);
    AiGeneration::factory()->failed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/analytics')
        ->assertOk()
        ->assertSee('AI spend (all time)')
        ->assertSee('$0.0100')
        ->assertSee('AI spend — last 30 days');
});

it('renders empty states before any data exists', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/analytics')
        ->assertOk()
        ->assertSee('No exercise attempts recorded yet.')
        ->assertSee('No AI generations recorded in the last 30 days.');
});
