<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\Import\BookImporter;
use App\Services\Import\ImportReport;
use App\Services\Import\StructureDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly int $bookId,
        public readonly string $absolutePath,
        public readonly string $originalName,
        public readonly bool $detectAfter = true,
    ) {}

    public function handle(BookImporter $importer, StructureDetector $detector): ImportReport
    {
        $book = Book::findOrFail($this->bookId);

        $report = ImportReport::fromExtract(
            $importer->extract($book, $this->absolutePath, $this->originalName),
        );

        if (! $this->detectAfter) {
            return $report;
        }

        try {
            return $report->withDetection($detector->detect($book));
        } catch (\RuntimeException $e) {
            return $report->withWarning($e->getMessage());
        }
    }
}
