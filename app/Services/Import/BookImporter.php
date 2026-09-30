<?php

namespace App\Services\Import;

use App\Enums\ImportJobStatus;
use App\Enums\ImportJobType;
use App\Enums\PageFlagReason;
use App\Enums\PdfDocumentStatus;
use App\Models\Book;
use App\Models\ImportJob;
use App\Models\PageReviewFlag;
use App\Models\PdfDocument;
use App\Models\PdfPage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BookImporter
{
    public function __construct(private readonly PdfTextExtractor $extractor) {}

    /**
     * Copy a PDF into storage, extract every page, and store the results.
     *
     * @return array{document_id: int, pages: int, flagged: int}
     */
    public function extract(Book $book, string $sourcePath, string $originalName): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("File not found: {$sourcePath}");
        }

        $sha256 = hash_file('sha256', $sourcePath);

        $existing = $book->pdfDocuments()
            ->where('sha256', $sha256)
            ->where('status', PdfDocumentStatus::Extracted)
            ->first();

        if ($existing !== null) {
            return [
                'document_id' => $existing->id,
                'pages' => $existing->pages()->count(),
                'flagged' => PdfPage::where('pdf_document_id', $existing->id)->where('needs_review', true)->count(),
            ];
        }

        $path = 'books/'.($sha256 ?: Str::random(40)).'.pdf';
        $absoluteTarget = Storage::disk('local')->path($path);

        $sameFile = is_file($absoluteTarget) && realpath($sourcePath) === realpath($absoluteTarget);

        if (! $sameFile) {
            Storage::disk('local')->makeDirectory('books');

            if (! copy($sourcePath, $absoluteTarget)) {
                throw new RuntimeException('Could not copy the PDF into storage.');
            }
        }

        $document = $book->pdfDocuments()->create([
            'original_name' => $originalName,
            'path' => $path,
            'sha256' => (string) $sha256,
            'page_count' => 0,
            'status' => PdfDocumentStatus::Extracting,
        ]);

        $job = ImportJob::create([
            'pdf_document_id' => $document->id,
            'type' => ImportJobType::Extract,
            'status' => ImportJobStatus::Running,
            'payload' => ['path' => $path],
            'started_at' => now(),
            'attempts' => 1,
        ]);

        try {
            $result = $this->storePages($document, $sourcePath);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $document->forceFill(['status' => PdfDocumentStatus::Failed, 'error' => $e->getMessage()])->save();

            throw $e;
        }

        $job->markDone($result);

        return $result;
    }

    /**
     * @return array{document_id: int, pages: int, flagged: int}
     */
    private function storePages(PdfDocument $document, string $absolutePath): array
    {
        $minWords = (int) config('import.min_words_per_page', 4);

        $extracted = $this->extractor->extract($absolutePath);

        $document->forceFill([
            'page_count' => $extracted['page_count'],
            'status' => PdfDocumentStatus::Extracting,
            'error' => null,
        ])->save();

        $document->pages()->delete();

        $buffer = [];
        $flagged = 0;

        foreach ($extracted['pages'] as $index => $rawText) {
            $text = TextSanitizer::clean($rawText);
            $wordCount = $text === '' ? 0 : str_word_count(strip_tags($text));
            $needsReview = $wordCount < $minWords;

            $buffer[] = [
                'pdf_document_id' => $document->id,
                'page_pdf' => $index + 1,
                'page_printed' => PrintedPageDetector::fromText($text),
                'text' => $text,
                'word_count' => $wordCount,
                'extraction_quality' => round(min(1, $wordCount / 50), 2),
                'needs_review' => $needsReview,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($needsReview) {
                $flagged++;
            }

            if (count($buffer) === 50) {
                $this->flushPages($document, $buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->flushPages($document, $buffer);
        }

        $document->forceFill(['status' => PdfDocumentStatus::Extracted])->save();

        return [
            'document_id' => $document->id,
            'pages' => count($extracted['pages']),
            'flagged' => $flagged,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flushPages(PdfDocument $document, array $rows): void
    {
        PdfPage::insert($rows);

        $flaggedPdfPages = [];

        foreach ($rows as $row) {
            if ($row['needs_review'] === true) {
                $flaggedPdfPages[] = $row['page_pdf'];
            }
        }

        if ($flaggedPdfPages === []) {
            return;
        }

        $ids = PdfPage::query()
            ->where('pdf_document_id', $document->id)
            ->whereIn('page_pdf', $flaggedPdfPages)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        PageReviewFlag::insert($ids->map(fn ($id) => [
            'pdf_page_id' => $id,
            'reason' => PageFlagReason::LowText->value,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }
}
