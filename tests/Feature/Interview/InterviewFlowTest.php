<?php

use App\Enums\InterviewGrade;
use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Livewire\Learn\InterviewMode;
use App\Models\InterviewQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->guard()->logout();

    $this->get(route('interview'))->assertRedirect(route('login'));
});

test('renders the first published question with a topic filter', function () {
    InterviewQuestion::factory()->create([
        'topic' => 'oop',
        'ord' => 1,
        'question' => 'What is composition over inheritance?',
    ]);
    InterviewQuestion::factory()->create([
        'topic' => 'core',
        'ord' => 2,
        'question' => 'Difference between == and ===?',
    ]);

    $this->get(route('interview'))
        ->assertOk()
        ->assertSee('What is composition over inheritance?')
        ->assertSee('All topics')
        ->assertSee('Oop')
        ->assertSee('Core');
});

test('shows the empty state when no questions exist', function () {
    $this->get(route('interview'))
        ->assertOk()
        ->assertSee('No questions yet');
});

test('reveal shows the model answer and follow-ups', function () {
    InterviewQuestion::factory()->create([
        'ord' => 1,
        'question' => 'Explain encapsulation.',
        'model_answer' => 'Bundle state with the methods that protect it and hide the rest.',
        'follow_ups' => ['Where would you enforce it?'],
    ]);

    Livewire::test(InterviewMode::class)
        ->call('reveal')
        ->assertSee('Model answer')
        ->assertSee('Bundle state with the methods that protect it')
        ->assertSee('Where would you enforce it?');
});

test('self-grading shaky queues a spaced review item', function () {
    $question = InterviewQuestion::factory()->create(['ord' => 1]);

    Livewire::test(InterviewMode::class)
        ->call('reveal')
        ->call('grade', 'shaky');

    $item = ReviewItem::query()->sole();

    expect($item->user_id)->toBe($this->user->id)
        ->and($item->item_type)->toBe(ReviewItemType::Interview)
        ->and($item->item_id)->toBe($question->id)
        ->and($item->reason)->toBe('interview: shaky')
        ->and($item->status)->toBe(ReviewItemStatus::Due)
        ->and($item->due_at->format('Y-m-d'))->toBe(now()->addDays(InterviewGrade::Shaky->dueDelayDays())->format('Y-m-d'));
});

test('self-grading confident schedules the review further out than missed', function () {
    InterviewQuestion::factory()->create(['ord' => 1]);

    Livewire::test(InterviewMode::class)
        ->call('reveal')
        ->call('grade', 'confident');

    $confident = ReviewItem::query()->sole();

    $confident->delete();

    Livewire::test(InterviewMode::class)
        ->call('reveal')
        ->call('grade', 'missed');

    $missed = ReviewItem::query()->sole();

    expect($confident->due_at->gt($missed->due_at))->toBeTrue()
        ->and($missed->reason)->toBe('interview: missed');
});

test('grading before reveal does not queue anything', function () {
    InterviewQuestion::factory()->create(['ord' => 1]);

    Livewire::test(InterviewMode::class)->call('grade', 'shaky');

    expect(ReviewItem::query()->count())->toBe(0);
});

test('grading the same question again updates the existing item', function () {
    InterviewQuestion::factory()->create(['ord' => 1]);

    Livewire::test(InterviewMode::class)->call('reveal')->call('grade', 'missed');
    Livewire::test(InterviewMode::class)->call('reveal')->call('grade', 'confident');

    expect(ReviewItem::query()->count())->toBe(1)
        ->and(ReviewItem::query()->sole()->reason)->toBe('interview: confident');
});

test('topic filter switches to a question from that topic', function () {
    InterviewQuestion::factory()->create(['topic' => 'oop', 'ord' => 1, 'question' => 'OOP question here']);
    InterviewQuestion::factory()->create(['topic' => 'testing', 'ord' => 2, 'question' => 'Testing question here']);

    Livewire::test(InterviewMode::class)
        ->call('selectTopic', 'testing')
        ->assertSee('Testing question here')
        ->assertDontSee('OOP question here');
});

test('next cycles to the following question and resets the attempt', function () {
    InterviewQuestion::factory()->create(['ord' => 1, 'question' => 'First question']);
    InterviewQuestion::factory()->create(['ord' => 2, 'question' => 'Second question']);

    Livewire::test(InterviewMode::class)
        ->call('reveal')
        ->call('next')
        ->assertSee('Second question')
        ->assertDontSee('First question')
        ->assertSet('revealed', false)
        ->assertSet('attempt', '');
});
