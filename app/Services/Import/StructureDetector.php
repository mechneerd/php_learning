<?php

namespace App\Services\Import;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\PdfPage;
use App\Models\Section;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds the chapter/section skeleton from two independent signals:
 *
 *  1. the table of contents (titles + printed page numbers), and
 *  2. the PDF itself (chapter opener pages carry a per-chapter DOI,
 *     `978-1-4842-6791-2_N`, and headings are matched against page text).
 *
 * Printed page numbers are mapped onto PDF page numbers via the folio
 * detected on each page; sections fall back to heading search when the
 * folio map cannot resolve them.
 */
class StructureDetector
{
    private const DOI_ISBN = '978-1-4842-6791-2';

    public function __construct(private readonly TocParser $tocParser) {}

    /**
     * @return array{
     *     chapters: int,
     *     sections: int,
     *     located_by_heading: int,
     *     located_by_map: int,
     *     unlocated: int,
     *     openers_found: int
     * }
     */
    public function detect(Book $book, bool $replace = false): array
    {
        $document = $book->activePdfDocument;

        if ($document === null) {
            throw new RuntimeException("Book {$book->id} has no extracted PDF document.");
        }

        if ($replace) {
            Section::whereIn('chapter_id', $book->chapters()->pluck('id'))->delete();
            $book->chapters()->delete();
        } elseif ($book->chapters()->exists()) {
            throw new RuntimeException("Book {$book->id} already has chapters; rerun with --replace.");
        }

        /** @var list<PdfPage> $pages */
        $pages = $document->pages()->get()->values()->all();

        if ($pages === []) {
            throw new RuntimeException("Document {$document->id} has no extracted pages.");
        }

        $printedToPdf = $this->folioMap($pages);
        $openers = $this->chapterOpeners($pages);

        $firstOpener = $openers === [] ? count($pages) + 1 : min($openers);
        $tocText = implode("\n", array_slice(array_column($pages, 'text'), 0, $firstOpener - 1));
        $tocChapters = $this->tocParser->chapters($this->tocParser->parse($tocText));
        if ($tocChapters === []) {
            throw new RuntimeException('Table of contents could not be parsed.');
        }

        $report = [
            'chapters' => 0,
            'sections' => 0,
            'located_by_heading' => 0,
            'located_by_map' => 0,
            'unlocated' => 0,
            'openers_found' => count($openers),
        ];

        $folios = array_filter(array_column($pages, 'page_printed'));
        $lastPrinted = $folios === [] ? null : max($folios);

        foreach ($tocChapters as $index => $tocChapter) {
            $next = $tocChapters[$index + 1] ?? null;

            $openerPdf = $openers[$tocChapter['number']] ?? null;
            $nextOpenerPdf = $next !== null ? ($openers[$next['number']] ?? null) : null;

            $chapter = Chapter::updateOrCreate(
                ['book_id' => $book->id, 'number' => $tocChapter['number']],
                [
                    'title' => $tocChapter['title'],
                    'slug' => Str::slug($tocChapter['title']),
                    'ord' => $index,
                    'page_printed_from' => $tocChapter['page'],
                    'page_printed_to' => $next !== null ? $next['page'] - 1 : $lastPrinted,
                    'page_pdf_from' => $openerPdf,
                    'page_pdf_to' => $nextOpenerPdf !== null
                        ? $nextOpenerPdf - 1
                        : ($document->page_count ?: null),
                ],
            );

            $report['chapters']++;

            $from = $chapter->page_pdf_from;
            $to = $chapter->page_pdf_to ?? $document->page_count;
            $usedSlugs = [];

            foreach ($tocChapter['sections'] as $sIndex => $tocSection) {
                $nextSection = $tocChapter['sections'][$sIndex + 1] ?? null;

                [$pagePdf, $how] = $this->locateSection(
                    $tocSection['title'],
                    $from,
                    $to,
                    $pages,
                    $tocSection['page'],
                    $printedToPdf,
                    $sIndex === 0 ? $from : null,
                );

                match ($how) {
                    'heading' => $report['located_by_heading']++,
                    'map' => $report['located_by_map']++,
                    default => $report['unlocated']++,
                };

                $parentId = null;

                if ($tocSection['parent'] !== null && isset($tocChapter['sections'][$tocSection['parent']])) {
                    $parent = $chapter->sections()->where('ord', $tocSection['parent'])->first();
                    $parentId = $parent?->id;
                }

                $slug = $this->uniqueSlug($tocSection['title'], $usedSlugs);

                $chapter->sections()->updateOrCreate(
                    ['ord' => $sIndex],
                    [
                        'title' => $tocSection['title'],
                        'slug' => $slug,
                        'number' => null,
                        'level' => $tocSection['level'],
                        'parent_id' => $parentId,
                        'page_printed_from' => $tocSection['page'],
                        'page_printed_to' => $nextSection !== null
                            ? max($tocSection['page'], $nextSection['page'] - 1)
                            : $chapter->page_printed_to,
                        'page_pdf_from' => $pagePdf,
                        'page_pdf_to' => null,
                    ],
                );

                $report['sections']++;
            }
        }

        $this->closeSectionRanges($book);

        return $report;
    }

    /**
     * @param  list<PdfPage>  $pages
     * @return array<int, int> printed page => PDF page
     */
    private function folioMap(array $pages): array
    {
        $map = [];

        foreach ($pages as $page) {
            if ($page->page_printed !== null && ! isset($map[$page->page_printed])) {
                $map[$page->page_printed] = $page->page_pdf;
            }
        }

        return $map;
    }

    /**
     * Chapter opener pages carry a DOI whose suffix is the chapter number.
     * Appendices reuse the sequence (23, 24) and are ignored here.
     *
     * @param  list<PdfPage>  $pages
     * @return array<int, int> chapter number => PDF page (1-based)
     */
    private function chapterOpeners(array $pages): array
    {
        $openers = [];
        $pattern = '#doi\.org/10\.1007/'.preg_quote(self::DOI_ISBN, '#').'_(\d+)#i';

        foreach ($pages as $page) {
            if (preg_match($pattern, (string) $page->text, $matches) === 1) {
                $openers[(int) $matches[1]] = $page->page_pdf;
            }
        }

        unset($openers[23], $openers[24]);

        ksort($openers);

        return $openers;
    }

    /**
     * @param  list<PdfPage>  $pages
     * @param  array<int, int>  $printedToPdf
     * @return array{0: int|null, 1: 'heading'|'map'|'none'}
     */
    private function locateSection(
        string $title,
        ?int $fromPdf,
        ?int $toPdf,
        array $pages,
        int $printedPage,
        array $printedToPdf,
        ?int $fallbackPdf = null,
    ): array {
        $normalized = $this->normalize($title);

        foreach ($pages as $page) {
            if ($fromPdf !== null && $page->page_pdf < $fromPdf) {
                continue;
            }

            if ($toPdf !== null && $page->page_pdf > $toPdf) {
                break;
            }

            if ($this->pageHasHeading((string) $page->text, $normalized)) {
                return [$page->page_pdf, 'heading'];
            }
        }

        if (isset($printedToPdf[$printedPage])) {
            return [$printedToPdf[$printedPage], 'map'];
        }

        return [$fallbackPdf, $fallbackPdf !== null ? 'map' : 'none'];
    }

    private function pageHasHeading(string $text, string $normalizedTitle): bool
    {
        foreach (explode("\n", $text) as $line) {
            if ($this->normalize($line) === $normalizedTitle) {
                return true;
            }
        }

        return false;
    }

    /**
     * pdftotext splits drop-cap headings ("T he Problem") and inserts stray
     * spaces; stripping all whitespace makes heading comparison reliable.
     */
    private function normalize(string $value): string
    {
        $value = str_replace(["\u{FFFD}", "\u{00A0}"], ' ', $value);

        return mb_strtolower(preg_replace('/\s+/', '', $value) ?? '');
    }

    /**
     * Sections repeat titles inside a chapter ("Summary"), so slugs are
     * disambiguated with a numeric suffix.
     *
     * @param  list<string>  $used
     */
    private function uniqueSlug(string $title, array &$used): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'section';
        }

        $slug = $base;
        $suffix = 1;

        while (in_array($slug, $used, true)) {
            $slug = $base.'-'.($suffix++);
        }

        $used[] = $slug;

        return $slug;
    }

    /**
     * Fill page_pdf_to for every section from the next section's start.
     */
    private function closeSectionRanges(Book $book): void
    {
        foreach ($book->chapters as $chapter) {
            $sections = $chapter->sections()->orderBy('ord')->get();

            foreach ($sections as $i => $section) {
                $next = $sections[$i + 1] ?? null;
                $end = $next?->page_pdf_from !== null
                    ? max((int) $section->page_pdf_from, $next->page_pdf_from - 1)
                    : $chapter->page_pdf_to;

                if ($section->page_pdf_to !== $end) {
                    $section->forceFill(['page_pdf_to' => $end])->save();
                }
            }
        }
    }
}
