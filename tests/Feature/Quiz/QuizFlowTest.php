<?php

use App\Enums\ContentStatus;
use App\Enums\QuizQuestionType;
use App\Livewire\Learn\QuizRunner;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->lesson = Lesson::factory()->create([
        'status' => ContentStatus::Published,
        'title' => 'Objects and classes',
    ]);

    $this->conceptA = Concept::factory()->create(['slug' => 'object-identity']);
    $this->conceptB = Concept::factory()->create(['slug' => 'encapsulation']);

    $this->question1 = makeQuestion(
        lesson: $this->lesson,
        ord: 1,
        concept: $this->conceptA,
        stem: 'What does `new` return?',
        correct: 'An object',
        wrong: 'A class',
    );
    $this->question2 = makeQuestion(
        lesson: $this->lesson,
        ord: 2,
        concept: $this->conceptB,
        stem: 'Which keyword hides internals?',
        correct: 'private',
        wrong: 'public',
    );

    $this->correctOption1 = $this->question1->options()->where('is_correct', true)->sole();
    $this->wrongOption2 = $this->question2->options()->where('is_correct', false)->sole();
});

function makeQuestion(Lesson $lesson, int $ord, Concept $concept, string $stem, string $correct, string $wrong): QuizQuestion
{
    $question = QuizQuestion::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $concept->id,
        'type' => QuizQuestionType::Mcq,
        'stem' => $stem,
        'ord' => $ord,
    ]);

    QuizOption::query()->create(['question_id' => $question->id, 'text' => $wrong, 'is_correct' => false, 'ord' => 1]);
    QuizOption::query()->create(['question_id' => $question->id, 'text' => $correct, 'is_correct' => true, 'ord' => 2]);

    return $question;
}

it('serves the lesson quiz page with progress', function () {
    $this->get(route('quiz.show', $this->lesson))
        ->assertOk()
        ->assertSee('Question 1 of 2')
        ->assertSee('What does `new` return?');
});

it('serves the global quiz page', function () {
    $this->get(route('quiz'))->assertOk();
});

it('scores a correct option and shows instant feedback', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->assertSet('lastCorrect', true)
        ->assertSet('index', 0);
});

it('ignores a second submit for an already answered question', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->set('option_id', (string) $this->wrongOption2->id)
        ->call('submit')
        ->assertSet('answered', fn (array $answered): bool => count($answered) === 1);
});

it('only moves forward and never past the last question', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->call('next')
        ->assertSet('index', 1)
        ->call('next')
        ->assertSet('index', 1);
});

it('rejects submitting without a selection', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->call('submit')
        ->assertHasErrors('option_id');
});

it('persists the attempt and answers when the quiz finishes', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->call('next')
        ->set('option_id', (string) $this->wrongOption2->id)
        ->call('submit')
        ->assertSet('report.score', 50.0)
        ->assertSet('report.is_best', true)
        ->assertSee('Weak concepts');

    $attempt = QuizAttempt::query()->sole();

    expect($attempt->score)->toBe(50.0)
        ->and($attempt->total)->toBe(2)
        ->and($attempt->correct_count)->toBe(1)
        ->and($attempt->weak_concepts)->toBe(['encapsulation'])
        ->and($attempt->answers()->count())->toBe(2);
});

it('flags only the weak concept in the report', function () {
    Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->call('next')
        ->set('option_id', (string) $this->wrongOption2->id)
        ->call('submit')
        ->assertSet('report.weak_concepts', ['encapsulation']);
});

it('grades short answers case-insensitively against the key', function () {
    $lesson = Lesson::factory()->create(['status' => ContentStatus::Published]);

    $question = QuizQuestion::factory()->create([
        'lesson_id' => $lesson->id,
        'concept_id' => $this->conceptA->id,
        'type' => QuizQuestionType::ShortAnswer,
        'stem' => 'A blueprint for an object is a ____.',
        'ord' => 1,
    ]);
    QuizOption::query()->create([
        'question_id' => $question->id,
        'text' => 'class',
        'is_correct' => true,
        'ord' => 1,
    ]);

    Livewire::test(QuizRunner::class, ['lesson' => $lesson])
        ->set('answer_text', 'CLASS')
        ->call('submit')
        ->assertSet('lastCorrect', true);
});

it('resets the run on retry', function () {
    $component = Livewire::test(QuizRunner::class, ['lesson' => $this->lesson])
        ->set('option_id', (string) $this->correctOption1->id)
        ->call('submit')
        ->call('next')
        ->set('option_id', (string) $this->wrongOption2->id)
        ->call('submit')
        ->assertSet('report.score', 50.0);

    $component
        ->call('retry')
        ->assertSet('report', null)
        ->assertSet('index', 0)
        ->assertSet('answered', []);

    expect(QuizAttempt::query()->count())->toBe(1);
});
