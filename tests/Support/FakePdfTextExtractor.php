<?php

namespace Tests\Support;

use App\Services\Import\PdfTextExtractor;

/**
 * Deterministic stand-in for pdftotext so tests never shell out.
 *
 * The fixture mirrors the real book: a dot-leader table of contents,
 * chapter opener pages carrying a per-chapter DOI, a folio at the bottom of
 * every page, and drop-cap headings ("T he Problem").
 */
class FakePdfTextExtractor extends PdfTextExtractor
{
    /**
     * @param  list<string>  $pages
     */
    public function __construct(private readonly array $pages = []) {}

    public static function book(): self
    {
        // The real PDF yields U+FFFD dot leaders, turned into spaces by TextSanitizer.
        $line = fn (string $title, string $page, int $width = 40): string => $title.str_repeat("\u{FFFD}", $width).$page;

        return new self([
            // 1 — table of contents
            implode("\n", [
                'Table of Contents',
                $line('About the Author ', '  xix'),
                $line('Part I:  Objects ', '  1'),
                $line('Chapter 1:  PHP: Design and Management ', '  3'),
                '  '.$line('The Problem ', '  3'),
                '  '.$line('About This Book ', '  8'),
                '       '.$line('Objects ', '  8'),
                '       '.$line('Patterns ', '  9'),
                '  '.$line('Summary ', '  11'),
                '  '.$line('Summary ', '  12'),
                $line('Chapter 2:  PHP and Objects ', '  13'),
                '  '.$line('The Accidental Success of PHP Objects ', '  13'),
                '  '.$line('Objects and Classes ', '  15'),
                '  '.$line('Summary ', '  19'),
                $line('Index ', '  779'),
            ]),

            // 2 — front matter prose, no entries
            implode("\n", [
                'Preface',
                'This book is about objects, patterns, and practice.',
            ]),

            // 3 — chapter 1 opener (DOI _1), drop-cap heading, folio 3
            implode("\n", [
                'CHAPTER 1',
                'PHP: Design and',
                'Management',
                '',
                'T he Problem',
                '',
                'The problem is that PHP is just too easy. It tempts you to try out',
                'your ideas and flatters you with good results.',
                '',
                '                                                 3',
                '© Matt Zandstra 2021',
                'M. Zandstra, PHP 8 Objects, Patterns, and Practice, https://doi.org/10.1007/978-1-4842-6791-2_1',
            ]),

            // 4
            implode("\n", [
                '                                     Chapter 1   PHP: Design and Management',
                '',
                'About This Book',
                '',
                'I cover objects first, then patterns, and close with practice.',
                '',
                '                                                 4',
            ]),

            // 5 — two headings on one page
            implode("\n", [
                '                                     Chapter 1   PHP: Design and Management',
                '',
                'Objects',
                '',
                'An object bundles state and behaviour.',
                '',
                'Patterns',
                '',
                'A pattern names a recurring solution.',
                '',
                '                                                 5',
            ]),

            // 6
            implode("\n", [
                '                                     Chapter 1   PHP: Design and Management',
                '',
                'Summary',
                '',
                'This chapter introduced the shape of the book.',
                '',
                '                                                 6',
            ]),

            // 7 — chapter 2 opener (DOI _2), folio 7
            implode("\n", [
                'CHAPTER 2',
                'PHP and Objects',
                '',
                'The Accidental Success of PHP Objects',
                '',
                'PHP grew object support in small steps.',
                '',
                '                                                 7',
                '© Matt Zandstra 2021',
                'M. Zandstra, PHP 8 Objects, Patterns, and Practice, https://doi.org/10.1007/978-1-4842-6791-2_2',
            ]),

            // 8
            implode("\n", [
                '                                     Chapter 2   PHP and Objects',
                '',
                'Objects and Classes',
                '',
                'Classes describe objects; objects are instances.',
                '',
                '                                                 8',
            ]),

            // 9 — chapter 2 summary
            implode("\n", [
                '                                     Chapter 2   PHP and Objects',
                '',
                'Summary',
                '',
                'Object support has improved with every release.',
                '',
                '                                                 9',
            ]),

            // 10 — near-empty page, flagged for review
            ' ',
        ]);
    }

    public function extract(string $absolutePath): array
    {
        return ['page_count' => count($this->pages), 'pages' => $this->pages];
    }

    public function pageCount(string $absolutePath): int
    {
        return count($this->pages);
    }
}
