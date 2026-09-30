<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\Import\StructureDetector;
use Illuminate\Console\Command;

class DetectStructure extends Command
{
    protected $signature = 'book:detect {--book= : Book ID} {--replace : Drop and rebuild existing chapters}';

    protected $description = 'Derive the chapter and section skeleton from the table of contents and the PDF itself';

    public function handle(StructureDetector $detector): int
    {
        $book = $this->option('book')
            ? Book::find((int) $this->option('book'))
            : Book::query()->latest('id')->first();

        if ($book === null) {
            $this->error('No book found. Run book:import first.');

            return self::FAILURE;
        }

        $this->info("Book #{$book->id}: {$book->title}");

        try {
            $report = $detector->detect($book, (bool) $this->option('replace'));
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
