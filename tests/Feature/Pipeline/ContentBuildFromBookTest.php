<?php

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\ProvenanceSource;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodeExample;
use App\Models\Concept;
use App\Models\ContentVersion;
use App\Models\Exercise;
use App\Models\ExerciseTest;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\Stage;
use App\Models\User;

/**
 * One chapter with a single section, two extracted pages (a listing, a
 * continuation page with header and folio noise) and three published
 * concepts, one of which the lesson text names.
 */
function contentBuildFixture(): Lesson
{
    User::factory()->admin()->create();

    $book = Book::factory()->create();
    $document = PdfDocument::factory()->create(['book_id' => $book->id, 'page_count' => 2]);

    $pageOne = implode("\n", [
        'CHAPTER 1',
        '',
        'Objects in PHP',
        '',
        'A class blueprint for an object lets you create many objects from one definition.',
        'You declare a class with the class keyword and instantiate it with new.',
        '',
        '    A second paragraph begins here after a sentence that ends with a period.',
        '',
        '// listing 1.1',
        '<?php',
        'class Thing {',
        '    public function run() {',
        "        return 'ok';",
        '    }',
        '}',
        '',
    ]);

    $pageTwo = implode("\n", [
        'Chapter 1   Objects and Classes',
        '',
        '    The Thing object is available to every script that includes this file.',
        '12',
        '',
    ]);

    PdfPage::factory()->create([
        'pdf_document_id' => $document->id,
        'page_pdf' => 1,
        'page_printed' => 1,
        'text' => $pageOne,
        'word_count' => 60,
    ]);
    PdfPage::factory()->create([
        'pdf_document_id' => $document->id,
        'page_pdf' => 2,
        'page_printed' => 2,
        'text' => $pageTwo,
        'word_count' => 20,
    ]);

    $chapter = Chapter::factory()->create([
        'book_id' => $book->id,
        'number' => 1,
        'title' => 'Objects and Classes',
        'ord' => 0,
        'page_printed_from' => 1,
        'page_printed_to' => 2,
        'page_pdf_from' => 1,
        'page_pdf_to' => 2,
    ]);

    $section = Section::factory()->create([
        'chapter_id' => $chapter->id,
        'title' => 'Objects in PHP',
        'level' => 1,
        'ord' => 1,
        'page_printed_from' => 1,
        'page_printed_to' => 2,
        'page_pdf_from' => 1,
        'page_pdf_to' => 2,
    ]);

    $lesson = Lesson::factory()->create([
        'stage_id' => Stage::factory()->create()->id,
        'chapter_id' => $chapter->id,
        'section_id' => $section->id,
        'title' => 'Objects in PHP',
        'summary' => 'Declare a class and instantiate an object.',
        'status' => ContentStatus::Published,
        'page_printed_from' => 1,
        'page_printed_to' => 2,
        'page_pdf_from' => 1,
        'page_pdf_to' => 2,
    ]);

    Concept::factory()->create([
        'slug' => 'class-blueprint',
        'name' => 'Class Blueprint',
        'definition' => 'A class blueprint defines state and behaviour for objects.',
        'status' => ContentStatus::Published,
    ]);
    Concept::factory()->create([
        'slug' => 'interfaces',
        'name' => 'Interfaces',
        'definition' => 'An interface declares the methods a class must implement.',
        'status' => ContentStatus::Published,
    ]);
    Concept::factory()->create([
        'slug' => 'traits',
        'name' => 'Traits',
        'definition' => 'A trait bundles reusable methods for many classes.',
        'status' => ContentStatus::Published,
    ]);

    return $lesson;
}

it('rebuilds a lesson from the book pages and approves every derived entity', function () {
    $lesson = contentBuildFixture();

    $this->artisan('content:build', ['--lesson' => $lesson->id])
        ->expectsOutputToContain('lesson #'.$lesson->id)
        ->expectsOutputToContain('Approved')
        ->assertExitCode(0);

    $lesson->refresh();

    expect($lesson->status)->toBe(ContentStatus::Published)
        ->and($lesson->source)->toBe(ProvenanceSource::Book)
        ->and($lesson->ai_model)->toBeNull()
        ->and($lesson->page_pdf_from)->toBe(1)
        ->and($lesson->est_minutes)->toBeGreaterThanOrEqual(2);

    $blocks = LessonBlock::query()->where('lesson_id', $lesson->id)->orderBy('ord')->get();

    expect($blocks->count())->toBeGreaterThanOrEqual(7)
        ->and($blocks->first()?->payload['text'] ?? null)->toBe('📖 Core explanation')
        ->and($blocks->pluck('source')->map(fn (ProvenanceSource $source): string => $source->value)->unique()->all())
        ->toBe([ProvenanceSource::Book->value]);

    foreach ($blocks as $block) {
        expect($block->page_pdf_from)->not->toBeNull()
            ->and($block->page_printed_from)->not->toBeNull();
    }

    $headings = $blocks->filter(fn (LessonBlock $b) => $b->type === BlockType::Heading)
        ->pluck('payload.text')
        ->all();

    expect($headings)->toContain('💻 Code examples', '🧠 Key concepts', '✍️ Exercises', '❓ Quiz');

    $codeBlock = $blocks->first(fn (LessonBlock $b) => $b->type === BlockType::CodeExample);
    $example = $codeBlock !== null ? CodeExample::query()->find($codeBlock->payload['code_example_id']) : null;

    expect($example)->not->toBeNull()
        ->and($example->listing_ref)->toBe('1.1')
        ->and($example->source)->toBe(ProvenanceSource::Book)
        ->and($example->code)->toContain('class Thing');

    $exerciseIds = $blocks->first(fn (LessonBlock $b) => $b->type === BlockType::ExerciseRef)?->payload['ids'] ?? [];
    expect($exerciseIds)->not->toBeEmpty();

    $write = Exercise::query()->where('lesson_id', $lesson->id)->where('type', ExerciseType::Write)->first();

    expect($write)->not->toBeNull()
        ->and($write->source)->toBe(ProvenanceSource::Book)
        ->and($write->status)->toBe(ContentStatus::Published);

    expect(ExerciseTest::query()->where('exercise_id', $write->id)->count())->toBeGreaterThan(0);

    $quizIds = $blocks->first(fn (LessonBlock $b) => $b->type === BlockType::QuizRef)?->payload['ids'] ?? [];
    expect($quizIds)->not->toBeEmpty();

    $questions = QuizQuestion::query()->whereIn('id', $quizIds)->get();
    expect($questions->count())->toBeGreaterThanOrEqual(1);

    foreach ($questions as $question) {
        $options = QuizOption::query()->where('question_id', $question->id)->get();

        expect($options->count())->toBeGreaterThanOrEqual(2)
            ->and($options->where('is_correct', true)->count())->toBe(1)
            ->and($question->status)->toBe(ContentStatus::Published);
    }

    expect(Flashcard::query()->where('lesson_id', $lesson->id)->count())->toBeGreaterThan(0)
        ->and($lesson->concepts()->pluck('slug')->all())->toContain('class-blueprint');

    expect(ContentVersion::query()->where('entity_type', 'lesson')->where('entity_id', $lesson->id)->exists())->toBeTrue()
        ->and(ContentVersion::query()->where('entity_type', 'exercise')->exists())->toBeTrue()
        ->and(ContentVersion::query()->where('entity_type', 'quiz_question')->exists())->toBeTrue();
});

it('rolls a dry run back so the lesson keeps its existing content', function () {
    $lesson = contentBuildFixture();

    LessonBlock::query()->create([
        'lesson_id' => $lesson->id,
        'ord' => 1,
        'type' => BlockType::Heading,
        'payload' => ['text' => 'Sentinel'],
        'source' => ProvenanceSource::Ai,
        'page_printed_from' => 1,
        'page_printed_to' => 1,
        'page_pdf_from' => 1,
        'page_pdf_to' => 1,
    ]);

    $this->artisan('content:build', ['--lesson' => $lesson->id, '--dry-run' => true])
        ->expectsOutputToContain('[dry-run] Totals')
        ->assertExitCode(0);

    $lesson->refresh();
    $blocks = LessonBlock::query()->where('lesson_id', $lesson->id)->get();

    expect($blocks->count())->toBe(1)
        ->and($blocks->first()?->payload['text'])->toBe('Sentinel')
        ->and($blocks->first()?->source)->toBe(ProvenanceSource::Ai)
        ->and($lesson->concepts()->count())->toBe(0)
        ->and(ContentVersion::query()->where('entity_type', 'lesson')->exists())->toBeFalse();
});

it('reports a failure when a lesson has no section to slice', function () {
    $lesson = Lesson::factory()->create([
        'stage_id' => Stage::factory()->create()->id,
        'chapter_id' => null,
        'section_id' => null,
    ]);

    $this->artisan('content:build', ['--lesson' => $lesson->id])
        ->expectsOutputToContain('failed')
        ->assertExitCode(1);
});
