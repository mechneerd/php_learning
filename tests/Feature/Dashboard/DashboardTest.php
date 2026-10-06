<?php

use App\Models\Concept;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ReviewItem;
use App\Models\Stage;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('shows the progress, stages and due-today cards', function () {
    $this->actingAs(User::factory()->create());
    Stage::factory()->create(['number' => 0]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Overall progress')
        ->assertSee('Stages')
        ->assertSee('Due today')
        ->assertSee('Stage 0')
        ->assertSee('Now');
});

test('shows nothing due when the queue is empty', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nothing due yet');
});

test('counts due review items', function () {
    $user = User::factory()->create();
    ReviewItem::factory()->count(3)->create([
        'user_id' => $user->id,
        'due_at' => now()->subHour(),
    ]);
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('items waiting in your revision queue.');
});

test('lists weak concepts that still have attempts', function () {
    $user = User::factory()->create();
    $concept = Concept::factory()->create(['name' => 'Polymorphic relations']);
    $exercise = Exercise::factory()->create(['concept_id' => $concept->id]);

    ExerciseAttempt::query()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 1;',
        'result' => 'incorrect',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Weak concepts')
        ->assertSee('Polymorphic relations');
});

test('shows a one-day streak after today has activity', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    ExerciseAttempt::query()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 1;',
        'result' => 'correct',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('1 day streak');
});

test('shows recent activity entries', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['prompt' => 'Build a Greeter class']);

    ExerciseAttempt::query()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php echo 1;',
        'result' => 'correct',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('Build a Greeter class');
});
