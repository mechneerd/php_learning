<?php

namespace App\Services\Content;

use App\Models\Book;
use App\Models\PdfPage;
use App\Models\Section;

/**
 * Pulls raw page text for a section's PDF page range out of the imported
 * document - the book excerpt handed to Pipeline A prompts (docs/10).
 */
final class BookExcerpt
{
    /**
     * @param  int  $maxChars  hard cap so prompts stay inside budget
     */
    public function forSection(Section $section, int $maxChars = 6000): string
    {
        return $this->pages($section->page_pdf_from, $section->page_pdf_to, $maxChars);
    }

    public function forRange(?int $from, ?int $to, int $maxChars = 6000): string
    {
        return $this->pages($from, $to, $maxChars);
    }

    private function pages(?int $from, ?int $to, int $maxChars): string
    {
        if ($from === null) {
            return '';
        }

        $book = Book::query()->first();
        $documentId = $book?->source_pdf_document_id;

        if ($documentId === null) {
            $documentId = $book?->activePdfDocument?->id;
        }

        if ($documentId === null) {
            return '';
        }

        $text = PdfPage::query()
            ->where('pdf_document_id', $documentId)
            ->whereBetween('page_pdf', [$from, $to ?? $from])
            ->orderBy('page_pdf')
            ->pluck('text')
            ->implode("\n");

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }

        return mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars) : $text;
    }
}
