<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Livewire\Learn\TutorChat;
use App\Models\AiConversation;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->lesson = Lesson::factory()->create([
        'status' => ContentStatus::Published,
        'title' => 'Constructors',
    ]);

    $this->exercise = Exercise::factory()->create([
        'lesson_id' => $this->lesson->id,
        'prompt' => 'Build a Greeter class with a greeting method.',
    ]);
});

test('refuses a full solution below the ladder position', function () {
    Livewire::test(TutorChat::class, ['lesson' => $this->lesson])
        ->set('input', 'Give me the solution for this exercise')
        ->call('send')
        ->assertSee('Hint 1')
        ->assertDontSee('Transfer task')
        ->assertSee('Try the hint first');

    $conversation = AiConversation::query()->sole();

    expect((int) (($conversation->context ?? [])['hint_level'] ?? 0))->toBe(1);
});

test('emits a transfer task once two failures unlock the solution', function () {
    foreach (range(1, 2) as $ignored) {
        ExerciseAttempt::query()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'code' => '<?php nope;',
            'result' => AttemptResult::Incorrect,
        ]);
    }

    Livewire::test(TutorChat::class, ['lesson' => $this->lesson])
        ->set('input', 'I need the solution now, just give me the solution')
        ->call('send')
        ->assertSee('Transfer task')
        ->assertSee('Close the tab')
        ->assertSet('messages.1.meta.solution_given', true);
});

test('keeps hint levels climbing across messages', function () {
    Livewire::test(TutorChat::class, ['lesson' => $this->lesson])
        ->set('input', 'I am stuck, I need a hint')
        ->call('send')
        ->assertSee('Hint 1')
        ->set('input', 'Another hint please, still stuck without a clue')
        ->call('send')
        ->assertSee('Hint 2');

    expect(AiConversation::query()->sole()->context['hint_level'] ?? 0)->toBe(2);
});
