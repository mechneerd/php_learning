<?php

namespace App\Console\Commands;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Services\Import\BookImporter;
use App\Services\Import\StructureDetector;
use Illuminate\Console\Command;

class ImportBook extends Command
{
    protected $signature = 'book:import
        {path : Absolute path to the source PDF}
        {--title= : Override the book title}
        {--detect : Detect chapters and sections immediately after extraction}';

    protected $description = 'Store a book PDF, extract text page by page, and flag pages for review';

    public function handle(BookImporter $importer, StructureDetector $detector): int
    {
        $title = $this->option('title') ?: (string) config('import.book_title', 'Untitled book');

        $book = Book::firstOrCreate(
            ['title' => $title],
            ['status' => BookStatus::Active],
        );

        $this->info("Book #{$book->id}: {$book->title}");
        $this->line('Extracting pages...');

        $result = $importer->extract($book, (string) $this->argument('path'), basename((string) $this->argument('path')));

        $this->info("Extracted {$result['pages']} pages into document #{$result['document_id']} ({$result['flagged']} flagged).");

        if ($this->option('detect')) {
            return $this->runDetect($detector, $book, false);
        }

        return self::SUCCESS;
    }

    private function runDetect(StructureDetector $detector, Book $book, bool $replace): int
    {
        $this->line('Detecting chapters and sections...');

        try {
            $report = $detector->detect($book, $replace);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Chapters: %d, sections: %d (openers found: %d)',
            $report['chapters'],
            $report['sections'],
            $report['openers_found'],
        ));

        $this->line(sprintf(
            'Located by heading: %d, by folio map: %d, unlocated: %d',
            $report['located_by_heading'],
            $report['located_by_map'],
            $report['unlocated'],
        ));

        return self::SUCCESS;
    }
}
