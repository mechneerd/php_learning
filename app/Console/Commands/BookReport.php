<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\PageReviewFlag;
use Illuminate\Console\Command;

class BookReport extends Command
{
    protected $signature = 'book:report {--book= : Book ID}';

    protected $description = 'Summarise import, structure, and content state for a book';

    public function handle(): int
    {
        $book = $this->option('book')
            ? Book::find((int) $this->option('book'))
            : Book::query()->latest('id')->first();

        if ($book === null) {
            $this->error('No book found.');

            return self::FAILURE;
        }

        $this->info("Book #{$book->id}: {$book->title} [{$book->status->value}]");
        $this->newLine();

        $rows = [];

        foreach ($book->pdfDocuments as $document) {
            $pages = $document->pages()->count();
            $noFolio = $document->pages()->whereNull('page_printed')->count();
            $flagged = $document->pages()->where('needs_review', true)->count();

            $rows[] = [
                $document->id,
                $document->original_name,
                $document->status->value,
                $document->page_count,
                $pages,
                $noFolio,
                $flagged,
            ];
        }

        $this->table(
            ['doc', 'name', 'status', 'pdf pages', 'stored', 'no folio', 'flagged'],
            $rows,
        );

        $chapters = $book->chapters()->count();
        $sections = $book->chapters()->withCount('sections')->get()->sum('sections_count');
        $openWithoutPdf = $book->chapters()->whereNull('page_pdf_from')->count();

        $this->line("Chapters: {$chapters} (missing PDF start page: {$openWithoutPdf})");
        $this->line("Sections: {$sections}");
        $this->newLine();

        $lessons = $book->chapters()->with('lessons')->get()->flatMap(fn ($chapter) => $chapter->lessons);

        $byStatus = $lessons->countBy(fn ($lesson) => $lesson->status->value);
        $bySource = $lessons->countBy(fn ($lesson) => $lesson->source->value);
        $withoutCitation = $lessons->filter(fn ($lesson) => $lesson->citation() === null)->count();

        $this->line('Lessons: '.($lessons->count() ?: 0).' total');

        foreach (ContentStatus::cases() as $status) {
            $this->line(sprintf('  %-10s %d', $status->value.':', $byStatus->get($status->value, 0)));
        }

        foreach (['book', 'ai', 'mixed'] as $source) {
            $this->line(sprintf('  source %-4s %d', $source.':', $bySource->get($source, 0)));
        }

        $this->line("  without citation: {$withoutCitation}");

        $flags = PageReviewFlag::whereNull('resolved_at')->count();
        $this->newLine();
        $this->line("Unresolved page flags: {$flags}");

        return self::SUCCESS;
    }
}
