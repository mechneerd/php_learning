<?php

namespace App\Jobs;

use App\Enums\ImportJobStatus;
use App\Enums\ImportJobType;
use App\Models\Book;
use App\Models\ImportJob;
use App\Services\Import\ImportReport;
use App\Services\Import\StructureDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectStructureJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $bookId,
        public readonly bool $replace = false,
    ) {}

    public function handle(StructureDetector $detector): ImportReport
    {
        $book = Book::findOrFail($this->bookId);

        $document = $book->activePdfDocument;

        if ($document === null) {
            throw new \RuntimeException("Book {$book->id} has no extracted PDF document.");
        }

        $job = ImportJob::create([
            'pdf_document_id' => $document->id,
            'type' => ImportJobType::DetectStructure,
            'status' => ImportJobStatus::Running,
            'started_at' => now(),
            'attempts' => 1,
        ]);

        try {
            $report = $detector->detect($book, $this->replace);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());

            throw $e;
        }

        $job->markDone($report);

        return ImportReport::fromExtract([
            'document_id' => $document->id,
            'pages' => 0,
            'flagged' => 0,
        ])->withDetection($report);
    }
}
