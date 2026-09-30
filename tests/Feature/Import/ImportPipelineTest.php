<?php

use App\Enums\PdfDocumentStatus;
use App\Jobs\ExtractPdfJob;
use App\Models\Book;
use App\Models\PageReviewFlag;
use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Services\Import\BookImporter;
use App\Services\Import\PdfTextExtractor;
use App\Services\Import\StructureDetector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfTextExtractor;

beforeEach(function () {
    Storage::fake('local');
    app()->instance(PdfTextExtractor::class, FakePdfTextExtractor::book());

    $this->pdfPath = sys_get_temp_dir().'/php-learning-fake-book.pdf';
    file_put_contents($this->pdfPath, '%PDF-1.4 fake book for tests');
});

afterEach(function () {
    @unlink($this->pdfPath);
});

it('extracts pages, detects folios, and flags empty pages', function () {
    $book = Book::factory()->create();

    $report = app(BookImporter::class)->extract($book, $this->pdfPath, 'book.pdf');

    expect($report['pages'])->toBe(10)
        ->and($report['flagged'])->toBe(1);

    $document = PdfDocument::find($report['document_id']);

    expect($document->status)->toBe(PdfDocumentStatus::Extracted)
        ->and($document->page_count)->toBe(10)
        ->and($document->pages()->count())->toBe(10);

    $page3 = $document->pages()->where('page_pdf', 3)->first();

    expect($page3->page_printed)->toBe(3)
        ->and($page3->word_count)->toBeGreaterThan(3);

    $flagged = PdfPage::where('needs_review', true)->get();

    expect($flagged)->toHaveCount(1)
        ->and($flagged->first()->page_pdf)->toBe(10)
        ->and(PageReviewFlag::count())->toBe(1);
});

it('reuses an already extracted document for the same file', function () {
    $book = Book::factory()->create();
    $importer = app(BookImporter::class);

    $first = $importer->extract($book, $this->pdfPath, 'book.pdf');
    $second = $importer->extract($book, $this->pdfPath, 'book.pdf');

    expect($second['document_id'])->toBe($first['document_id'])
        ->and($book->pdfDocuments()->count())->toBe(1)
        ->and(PdfPage::count())->toBe(10);
});

it('builds chapters and sections from the toc and the pdf itself', function () {
    $book = Book::factory()->create();
    app(BookImporter::class)->extract($book, $this->pdfPath, 'book.pdf');

    $report = app(StructureDetector::class)->detect($book);

    expect($report['chapters'])->toBe(2)
        ->and($report['sections'])->toBe(9)
        ->and($report['openers_found'])->toBe(2)
        ->and($report['located_by_heading'])->toBe(9)
        ->and($report['unlocated'])->toBe(0);

    $chapters = $book->chapters()->orderBy('number')->get();

    expect($chapters[0])->toMatchArray([
        'number' => 1,
        'page_printed_from' => 3,
        'page_printed_to' => 12,
        'page_pdf_from' => 3,
    ]);

    $sections = $chapters[0]->sections()->orderBy('ord')->get();

    expect($sections->pluck('title')->all())
        ->toBe(['The Problem', 'About This Book', 'Objects', 'Patterns', 'Summary', 'Summary']);

    expect($sections[0]->page_pdf_from)->toBe(3)
        ->and($sections[1]->page_pdf_from)->toBe(4)
        ->and($sections[2]->page_pdf_from)->toBe(5)
        ->and($sections[2]->level)->toBe(2)
        ->and($sections[2]->parent_id)->toBe($sections[1]->id)
        ->and($sections[4]->page_pdf_from)->toBe(6)
        ->and($sections[4]->slug)->toBe('summary')
        ->and($sections[5]->slug)->toBe('summary-1')
        ->and($sections[4]->page_pdf_to)->toBe(6)
        ->and($sections[5]->page_pdf_from)->toBe(6)
        ->and($sections[5]->page_pdf_to)->toBe($chapters[0]->page_pdf_to);

    expect($chapters[1])->toMatchArray([
        'number' => 2,
        'page_printed_from' => 13,
        'page_pdf_from' => 7,
    ]);
});

it('refuses to detect twice unless replace is requested', function () {
    $book = Book::factory()->create();
    app(BookImporter::class)->extract($book, $this->pdfPath, 'book.pdf');

    $detector = app(StructureDetector::class);
    $detector->detect($book);

    expect(fn () => $detector->detect($book))->toThrow(RuntimeException::class);

    $report = $detector->detect($book, replace: true);

    expect($report['chapters'])->toBe(2)
        ->and($book->chapters()->count())->toBe(2);
});

it('runs the import, detect, and report commands end to end', function () {
    $exit = Artisan::call('book:import', [
        'path' => $this->pdfPath,
        '--detect' => true,
    ]);

    expect($exit)->toBe(0);

    $book = Book::firstOrFail();

    expect($book->chapters()->count())->toBe(2)
        ->and($book->chapters()->withCount('sections')->get()->sum('sections_count'))->toBe(9);

    Artisan::call('book:report');
    $output = Artisan::output();

    expect($output)->toContain('Chapters: 2')
        ->toContain('Sections: 9');

    $replaced = Artisan::call('book:detect', ['--replace' => true]);
    expect($replaced)->toBe(0);
});

it('fails detect cleanly when no book exists', function () {
    expect(Artisan::call('book:detect'))->toBe(1)
        ->and(Artisan::output())->toContain('No book found');
});

it('queues extraction as a job', function () {
    Queue::fake();

    $book = Book::factory()->create();

    ExtractPdfJob::dispatch($book->id, $this->pdfPath, 'book.pdf');

    Queue::assertPushed(ExtractPdfJob::class, fn ($job) => $job->bookId === $book->id);
});
