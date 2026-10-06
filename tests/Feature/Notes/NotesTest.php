<?php

use App\Enums\NoteKind;
use App\Livewire\Learn\NotesList;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('notes'))->assertRedirect(route('login'));
});

test('shows the empty state without notes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('notes'))
        ->assertOk()
        ->assertSee('No notes yet');
});

test('lists notes grouped by kind with links back to the lesson', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['slug' => 'ch3-constructors']);

    Note::factory()->create([
        'user_id' => $user->id,
        'noteable_type' => Lesson::class,
        'noteable_id' => $lesson->id,
        'kind' => NoteKind::Note,
        'body' => 'Constructors run on instantiation.',
    ]);
    Note::factory()->create([
        'user_id' => $user->id,
        'noteable_type' => Lesson::class,
        'noteable_id' => $lesson->id,
        'kind' => NoteKind::Highlight,
        'body' => 'Promotion avoids boilerplate.',
    ]);

    $this->actingAs($user);

    $this->get(route('notes'))
        ->assertOk()
        ->assertSee('Constructors run on instantiation.')
        ->assertSee('Promotion avoids boilerplate.')
        ->assertSee('Highlight')
        ->assertSee(route('lessons.show', 'ch3-constructors'));
});

test('deletes your own note', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(NotesList::class)->call('delete', $note->id);

    expect(Note::query()->whereKey($note->id)->exists())->toBeFalse();
});

test('cannot delete another user note', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    Livewire::test(NotesList::class)->call('delete', $note->id);

    expect(Note::query()->whereKey($note->id)->exists())->toBeTrue();
});

test('only shows your own notes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Note::factory()->create(['user_id' => $user->id, 'body' => 'My note about traits.']);
    Note::factory()->create(['user_id' => $other->id, 'body' => 'Someone else note about traits.']);

    $this->actingAs($user);

    $this->get(route('notes'))
        ->assertOk()
        ->assertSee('My note about traits.')
        ->assertDontSee('Someone else note about traits.');
});
