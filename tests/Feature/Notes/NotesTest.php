<?php

use App\Enums\ContentStatus;
use App\Enums\NoteKind;
use App\Livewire\Learn\LessonView;
use App\Livewire\Learn\NotesList;
use App\Models\Concept;
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

test('the lesson rail saves a personal note on the current lesson', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->set('railTab', 'notes')
        ->set('noteBody', 'A class is a blueprint for objects.')
        ->set('noteKind', 'important')
        ->call('saveNote')
        ->assertHasNoErrors()
        ->assertSee('A class is a blueprint for objects.');

    $note = Note::query()
        ->where('user_id', $user->id)
        ->where('noteable_type', Lesson::class)
        ->where('noteable_id', $lesson->id)
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->kind)->toBe(NoteKind::Important)
        ->and($note->body)->toBe('A class is a blueprint for objects.');
});

test('the lesson rail rejects an empty note', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->set('railTab', 'notes')
        ->call('saveNote')
        ->assertHasErrors(['noteBody']);

    expect(Note::query()->count())->toBe(0);
});

test('the lesson rail deletes only your own note on the lesson', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $mine = Note::factory()->create([
        'user_id' => $user->id,
        'noteable_type' => Lesson::class,
        'noteable_id' => $lesson->id,
    ]);
    $theirs = Note::factory()->create([
        'user_id' => $other->id,
        'noteable_type' => Lesson::class,
        'noteable_id' => $lesson->id,
    ]);

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->set('railTab', 'notes')
        ->call('deleteNote', $mine->id);

    expect(Note::query()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(Note::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

test('the concepts tab lists concepts linked to the lesson', function () {
    $user = User::factory()->create();
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $concept = Concept::factory()->create([
        'name' => 'Object Identity',
        'definition' => 'Two variables can point at the same object.',
    ]);

    $lesson->concepts()->sync([$concept->id => ['role' => 'core']]);

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->set('railTab', 'concepts')
        ->assertSee('Object Identity')
        ->assertSee('Two variables can point at the same object.')
        ->assertSee(route('concepts.show', $concept));
});
