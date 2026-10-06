<?php

use App\Enums\CardType;
use App\Enums\ContentStatus;
use App\Enums\HintGrade;
use App\Livewire\Learn\FlashcardSession;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->card1 = Flashcard::factory()->create([
        'card_type' => CardType::Definition,
        'front' => 'What is a class?',
        'back' => 'A blueprint for objects.',
        'ord' => 1,
    ]);
    $this->card2 = Flashcard::factory()->create([
        'card_type' => CardType::Difference,
        'front' => 'abstract vs interface?',
        'back' => 'Abstract may hold state; interfaces cannot.',
        'ord' => 2,
    ]);
});

it('serves the flashcards page', function () {
    $this->get(route('flashcards'))
        ->assertOk()
        ->assertSee('Tap or press space to flip');
});

it('shows the first card front and flips on demand', function () {
    Livewire::test(FlashcardSession::class)
        ->assertSet('index', 0)
        ->assertSee('What is a class?')
        ->call('flip')
        ->assertSet('flipped', true)
        ->assertSee('A blueprint for objects.');
});

it('ignores grading before the card is flipped', function () {
    Livewire::test(FlashcardSession::class)
        ->call('grade', 'ok')
        ->assertSet('reviewed', 0)
        ->assertSet('index', 0);

    expect(FlashcardReview::query()->count())->toBe(0);
});

it('schedules the first review at one day and advances', function () {
    Livewire::test(FlashcardSession::class)
        ->call('flip')
        ->call('grade', 'ok')
        ->assertSet('reviewed', 1)
        ->assertSet('index', 1)
        ->assertSet('flipped', false);

    $review = FlashcardReview::query()->sole();

    expect($review->card_id)->toBe($this->card1->id)
        ->and($review->grade)->toBe(HintGrade::Ok)
        ->and($review->interval_days)->toBe(1)
        ->and($review->next_review_at->isFuture())->toBeTrue();
});

it('shows the computed next intervals before grading', function () {
    Livewire::test(FlashcardSession::class)
        ->assertSet('nextIntervals.hard', 1)
        ->assertSet('nextIntervals.ok', 1)
        ->assertSet('nextIntervals.easy', 1);
});

it('multiplies the interval for an existing review', function () {
    FlashcardReview::query()->create([
        'user_id' => $this->user->id,
        'card_id' => $this->card1->id,
        'grade' => HintGrade::Ok,
        'interval_days' => 3,
        'ease' => 2.50,
        'next_review_at' => now()->subDay(),
        'reviewed_at' => now()->subDays(4),
    ]);

    Livewire::test(FlashcardSession::class)
        ->assertSet('queue.0', $this->card1->id)
        ->assertSet('nextIntervals.ok', 8)
        ->call('flip')
        ->call('grade', 'ok');

    expect(FlashcardReview::query()->sole()->interval_days)->toBe(8);
});

it('leaves future reviews out of the queue', function () {
    FlashcardReview::query()->create([
        'user_id' => $this->user->id,
        'card_id' => $this->card2->id,
        'grade' => HintGrade::Easy,
        'interval_days' => 4,
        'ease' => 2.50,
        'next_review_at' => now()->addDays(3),
        'reviewed_at' => now(),
    ]);

    Livewire::test(FlashcardSession::class)
        ->assertSet('queue', [$this->card1->id]);
});

it('excludes draft cards from the session', function () {
    $this->card2->update(['status' => ContentStatus::Draft]);

    Livewire::test(FlashcardSession::class)
        ->assertSet('queue', [$this->card1->id]);
});

it('completes the session when the queue is exhausted', function () {
    $component = Livewire::test(FlashcardSession::class)
        ->call('flip')
        ->call('grade', 'easy')
        ->call('flip')
        ->call('grade', 'easy')
        ->assertSet('reviewed', 2)
        ->assertSee('Session complete')
        ->assertSet('remaining', 0);

    expect(FlashcardReview::query()->count())->toBe(2);
});
