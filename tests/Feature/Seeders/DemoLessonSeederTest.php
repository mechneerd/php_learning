<?php

namespace Tests\Feature\Seeders;

use App\Enums\BlockType;
use App\Enums\CodeTier;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Chapter;
use App\Models\CodeExample;
use App\Models\Diagram;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Models\Section;
use Database\Seeders\DemoLessonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const QUOTE_SENTENCE = 'Classes and objects form the foundation of object-oriented programming in PHP, where every value of interest is built from a class that defines its shape and behaviour.';

function chapterWithSections(array $sectionDefs): Chapter
{
    $chapter = Chapter::factory()->create([
        'number' => 3,
        'title' => 'Object Basics',
        'slug' => 'object-basics',
        'page_printed_from' => 21,
        'page_printed_to' => 78,
        'page_pdf_from' => 41,
        'page_pdf_to' => 97,
    ]);

    foreach ($sectionDefs as $index => $def) {
        Section::factory()->create([
            'chapter_id' => $chapter->id,
            'title' => $def['title'],
            'slug' => $def['slug'],
            'level' => 1,
            'ord' => $def['ord'],
            'page_printed_from' => $def['page_printed_from'],
            'page_printed_to' => $def['page_printed_to'],
            'page_pdf_from' => $def['page_pdf_from'],
            'page_pdf_to' => $def['page_pdf_to'],
        ]);
    }

    return $chapter;
}

function attachQuotablePages(Chapter $chapter, Section $section): void
{
    $document = PdfDocument::factory()->create(['book_id' => $chapter->book_id]);
    $chapter->book()->update(['source_pdf_document_id' => $document->id]);

    PdfPage::factory()->create([
        'pdf_document_id' => $document->id,
        'page_pdf' => $section->page_pdf_from,
        'page_printed' => $section->page_printed_from,
        'text' => "Chapter 3 Object Basics\n\n".QUOTE_SENTENCE."\n\n// listing 03.01\n\npublic function demo() {}\n",
        'word_count' => 40,
    ]);
}

it('seeds chapter 3 lessons with book-backed content', function () {
    $chapter = chapterWithSections([
        ['title' => 'Classes and Objects', 'slug' => 'classes-and-objects', 'ord' => 0, 'page_printed_from' => 21, 'page_printed_to' => 22, 'page_pdf_from' => 41, 'page_pdf_to' => 42],
        ['title' => 'A First Class', 'slug' => 'a-first-class', 'ord' => 1, 'page_printed_from' => 22, 'page_printed_to' => 24, 'page_pdf_from' => 42, 'page_pdf_to' => 44],
    ]);

    attachQuotablePages($chapter, $chapter->sections()->first());

    $this->seed(DemoLessonSeeder::class);

    $intro = Lesson::query()->where('slug', 'ch3-classes-and-objects')->firstOrFail();
    $first = Lesson::query()->where('slug', 'ch3-a-first-class')->firstOrFail();

    expect($intro->status)->toBe(ContentStatus::Published)
        ->and($intro->section_id)->toBe($chapter->sections()->first()->id)
        ->and($intro->page_pdf_from)->toBe(41)
        ->and($intro->stage->slug)->toBe('object-core');

    $quote = LessonBlock::query()
        ->where('lesson_id', $intro->id)
        ->where('type', BlockType::BookQuote)
        ->firstOrFail();

    expect($quote->payload['text'])->toBe(QUOTE_SENTENCE)
        ->and($quote->source)->toBe(ProvenanceSource::Book);

    expect(
        LessonBlock::query()->where('lesson_id', $intro->id)->where('type', BlockType::Diagram)->count()
    )->toBe(1);

    expect(Diagram::query()->where('lesson_id', $intro->id)->firstOrFail()->mermaid_source)->toContain('flowchart TD');

    $example = CodeExample::query()
        ->where('lesson_id', $first->id)
        ->where('listing_ref', '03.01')
        ->firstOrFail();

    expect($example->source)->toBe(ProvenanceSource::Book)
        ->and($example->status)->toBe(ContentStatus::Published)
        ->and($example->tier)->toBe(CodeTier::Starter)
        ->and($example->code)->toContain('class ShopProduct');

    expect($intro->blocks()->where('type', BlockType::ExerciseRef)->exists())->toBeTrue();

    $codeBlock = LessonBlock::query()
        ->where('lesson_id', $first->id)
        ->where('type', BlockType::CodeExample)
        ->firstOrFail();

    expect($codeBlock->payload['code_example_id'])->toBe($example->id)
        ->and($codeBlock->source)->toBe(ProvenanceSource::Book);
});

it('is idempotent across runs', function () {
    $chapter = chapterWithSections([
        ['title' => 'Classes and Objects', 'slug' => 'classes-and-objects', 'ord' => 0, 'page_printed_from' => 21, 'page_printed_to' => 22, 'page_pdf_from' => 41, 'page_pdf_to' => 42],
    ]);

    attachQuotablePages($chapter, $chapter->sections()->first());

    $this->seed(DemoLessonSeeder::class);

    $counts = [
        Lesson::count(),
        LessonBlock::count(),
        CodeExample::count(),
        Diagram::count(),
    ];

    $this->seed(DemoLessonSeeder::class);

    expect([
        Lesson::count(),
        LessonBlock::count(),
        CodeExample::count(),
        Diagram::count(),
    ])->toBe($counts);
});

it('skips gracefully when chapter 3 has not been imported', function () {
    $this->seed(DemoLessonSeeder::class);

    expect(Lesson::count())->toBe(0);
});

it('skips sections that do not match', function () {
    chapterWithSections([
        ['title' => 'An Unrelated Heading', 'slug' => 'unrelated', 'ord' => 100, 'page_printed_from' => 1, 'page_printed_to' => 2, 'page_pdf_from' => 1, 'page_pdf_to' => 2],
    ]);

    $this->seed(DemoLessonSeeder::class);

    expect(Lesson::count())->toBe(0);
});
