<?php

use App\Enums\AttemptResult;
use App\Enums\ContentStatus;
use App\Enums\ReviewItemStatus;
use App\Enums\ReviewItemType;
use App\Livewire\Learn\RevisionQueue;
use App\Models\Concept;
use App\Models\ErrorPattern;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\InterviewQuestion;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->guard()->logout();

    $this->get(route('revision'))->assertRedirect(route('login'));
});

test('renders due items grouped by kind', function () {
    $concept = Concept::factory()->create(['name' => 'Composition']);

    ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'item_type' => ReviewItemType::Concept,
        'item_id' => $concept->id,
        'concept_id' => $concept->id,
        'reason' => 'weak_concept',
        'due_at' => now()->subHour(),
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Concepts')
        ->assertSee('Composition')
        ->assertSee('Snooze 1d')
        ->assertSee('Mark done');
});

test('shows the empty state with the next review time', function () {
    ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'due_at' => now()->addDays(2),
        'status' => ReviewItemStatus::Snoozed,
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Nothing due')
        ->assertSee('next review');
});

test('snoozing pushes the item a day forward', function () {
    $this->travelTo('2026-06-15 12:00:00');

    $item = ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'due_at' => now()->subHour(),
    ]);

    Livewire::test(RevisionQueue::class)
        ->call('snooze', $item->id);

    $item->refresh();
    expect($item->status)->toBe(ReviewItemStatus::Snoozed)
        ->and($item->due_at->isToday())->toBeFalse()
        ->and($item->due_at->isTomorrow())->toBeTrue();
});

test('marking an item done removes it from the queue', function () {
    $item = ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'due_at' => now()->subHour(),
    ]);

    Livewire::test(RevisionQueue::class)
        ->call('markDone', $item->id)
        ->assertSet('total', 0);

    expect($item->refresh()->status)->toBe(ReviewItemStatus::Done);
});

test('cannot snooze another user item', function () {
    $other = User::factory()->create();
    $item = ReviewItem::factory()->create([
        'user_id' => $other->id,
        'due_at' => now()->subHour(),
    ]);

    Livewire::test(RevisionQueue::class)->call('snooze', $item->id);

    expect($item->refresh()->status)->toBe(ReviewItemStatus::Due);
});

test('syncs due flashcards into the queue', function () {
    $card = Flashcard::factory()->create(['status' => ContentStatus::Published]);

    FlashcardReview::query()->create([
        'user_id' => $this->user->id,
        'card_id' => $card->id,
        'grade' => 'ok',
        'interval_days' => 1,
        'next_review_at' => now()->subHour(),
        'reviewed_at' => now()->subDays(2),
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Cards')
        ->assertSee($card->front);

    expect(ReviewItem::query()->where('item_type', ReviewItemType::Card->value)->count())->toBe(1);
});

test('syncs the latest failed exercise attempt into the queue', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    $exercise = Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'prompt' => 'Fix the broken counter loop.',
    ]);

    ExerciseAttempt::query()->create([
        'user_id' => $this->user->id,
        'exercise_id' => $exercise->id,
        'code' => '<?php',
        'result' => AttemptResult::Incorrect,
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Exercises')
        ->assertSee('Fix the broken counter loop.');
});

test('syncs quiz weak concepts until they reach practicing', function () {
    $concept = Concept::factory()->create(['name' => 'Encapsulation']);

    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);
    QuizAttempt::query()->create([
        'user_id' => $this->user->id,
        'lesson_id' => $lesson->id,
        'total' => 5,
        'score' => 40,
        'correct_count' => 2,
        'weak_concepts' => [$concept->slug],
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Encapsulation');

    expect(ReviewItem::query()->where('item_type', ReviewItemType::Concept->value)->count())->toBe(1);
});

test('renders due error patterns with a link to the pattern page', function () {
    $pattern = ErrorPattern::factory()->create(['status' => ContentStatus::Published]);

    ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'item_type' => ReviewItemType::Error,
        'item_id' => $pattern->id,
        'reason' => 'interview: missed',
        'due_at' => now()->subHour(),
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Errors')
        ->assertSee($pattern->name)
        ->assertSee(route('errors.show', $pattern->slug));
});

test('renders due interview questions with a link to interview mode', function () {
    $question = InterviewQuestion::factory()->create(['status' => ContentStatus::Published]);

    ReviewItem::factory()->create([
        'user_id' => $this->user->id,
        'item_type' => ReviewItemType::Interview,
        'item_id' => $question->id,
        'reason' => 'interview: shaky',
        'due_at' => now()->subHour(),
    ]);

    $this->get(route('revision'))
        ->assertOk()
        ->assertSee('Interviews')
        ->assertSee(mb_substr($question->question, 0, 40))
        ->assertSee(route('interview'));
});
