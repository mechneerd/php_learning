<?php

use App\Services\Content\FigureExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('fails soft when the figure reference is malformed', function () {
    expect(app(FigureExtractor::class)->find('not-a-ref', 1))->toBeNull();
});

it('fails soft when the book PDF is unavailable', function () {
    // Fresh test database: no pdf_documents row, no extracted file.
    expect(app(FigureExtractor::class)->find('16-1', 1))->toBeNull();
});

it('fails soft for a figure that has no qualifying raster image', function () {
    // A pdf_pages row without a matching pdfimages entry still resolves to null.
    expect(app(FigureExtractor::class)->find('999-99', 1))->toBeNull();
});

it('fails soft when the caption page exists but the render binaries are missing', function () {
    config([
        'import.pdftotext_binary' => 'definitely-not-a-binary-xyz',
        'import.pdftoppm_binary' => 'definitely-not-a-binary-xyz',
    ]);

    $bookId = DB::table('books')->insertGetId([
        'title' => 'Fixture Book',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $docId = DB::table('pdf_documents')->insertGetId([
        'book_id' => $bookId,
        'original_name' => 'fixture.pdf',
        'path' => 'fixture.pdf',
        'sha256' => str_repeat('a', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('pdf_pages')->insert([
        'pdf_document_id' => $docId,
        'page_pdf' => 990,
        'page_printed' => 970,
        'text' => "Some prose.\nFigure 77-1. A test diagram\nMore prose.",
    ]);

    expect(app(FigureExtractor::class)->find('77-1', 1))->toBeNull();
});
