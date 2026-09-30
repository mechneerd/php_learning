<?php

namespace App\Services\Import;

/**
 * Parses the book's table of contents out of extracted page text.
 *
 * Entry lines look like:
 *
 *   Chapter 1:  PHP: Design and Management ......... 3
 *     The Problem ................................... 3
 *          Objects ................................... 8
 *
 * Dot leaders are U+FFFD after pdftotext extraction, replaced with spaces by
 * TextSanitizer. Indent width varies per chapter (2 or 5 spaces for level one,
 * 7+ for level two), so levels are derived relative to each chapter's minimum
 * section indent rather than absolute whitespace.
 */
class TocParser
{
    private const MISS_LIMIT = 12;

    private const NON_TOC_TITLES = [
        'index', 'acknowledgments', 'introduction', 'about the author',
        'about the technical reviewer', 'preface',
    ];

    /**
     * @return list<array{
     *     kind: 'part'|'chapter'|'section'|'appendix',
     *     number: int|null,
     *     title: string,
     *     page: int,
     *     indent: int
     * }>
     */
    public function parse(string $text): array
    {
        $lines = explode("\n", $text);
        $start = $this->findTocStart($lines);

        if ($start === null) {
            return [];
        }

        $entries = [];
        $seen = false;
        $misses = 0;

        for ($i = $start + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(\s*)(.*\S)[\s.]{2,}(\d{1,4})\s*$/u', $line, $m) !== 1) {
                if ($seen && ++$misses >= self::MISS_LIMIT) {
                    break;
                }

                continue;
            }

            $misses = 0;
            $seen = true;
            $indent = strlen($m[1]);
            $page = (int) $m[3];

            // Leaders can survive extraction as U+FFFD or ellipses.
            $title = rtrim(trim(str_replace(["\u{FFFD}", "\u{00A0}", "\u{2026}"], ' ', $m[2])), " \t.");

            $entry = $this->classify($title, $page, $indent);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  list<array{kind: 'part'|'chapter'|'section'|'appendix', number: int|null, title: string, page: int, indent: int}>  $entries
     * @return list<array{
     *     number: int,
     *     title: string,
     *     page: int,
     *     sections: list<array{title: string, page: int, level: int, parent: int|null}>
     * }>
     */
    public function chapters(array $entries): array
    {
        /** @var list<array{number: int, title: string, page: int}> $chapters */
        $chapters = [];

        /** @var array<int, list<array{title: string, page: int, level: 1, parent: null, indent: int}>> $sections */
        $sections = [];

        $current = -1;

        foreach ($entries as $entry) {
            if ($entry['kind'] === 'chapter') {
                $current = count($chapters);
                $chapters[] = [
                    'number' => (int) $entry['number'],
                    'title' => $entry['title'],
                    'page' => $entry['page'],
                ];
                $sections[$current] = [];

                continue;
            }

            if ($entry['kind'] !== 'section' || $current < 0) {
                continue;
            }

            $sections[$current][] = [
                'title' => $entry['title'],
                'page' => $entry['page'],
                'level' => 1,
                'parent' => null,
                'indent' => $entry['indent'],
            ];
        }

        $result = [];

        foreach ($chapters as $index => $chapter) {
            $chapterSections = $this->resolveSectionLevels($sections[$index] ?? []);

            foreach ($chapterSections as $i => $section) {
                unset($chapterSections[$i]['indent']);
            }

            $result[] = [
                'number' => $chapter['number'],
                'title' => $chapter['title'],
                'page' => $chapter['page'],
                'sections' => $chapterSections,
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $lines
     */
    private function findTocStart(array $lines): ?int
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, 'table of contents') !== false) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{kind: 'part'|'chapter'|'section'|'appendix', number: int|null, title: string, page: int, indent: int}|null
     */
    private function classify(string $title, int $page, int $indent): ?array
    {
        if (preg_match('/^Part\s+[IVXLC]+:/i', $title) === 1) {
            return ['kind' => 'part', 'number' => null, 'title' => $title, 'page' => $page, 'indent' => $indent];
        }

        if (preg_match('/^Chapter\s+(\d+)\s*:\s*(.+)$/i', $title, $m) === 1) {
            return ['kind' => 'chapter', 'number' => (int) $m[1], 'title' => trim($m[2]), 'page' => $page, 'indent' => $indent];
        }

        if (preg_match('/^Appendix\s+[A-Z]/i', $title) === 1) {
            return ['kind' => 'appendix', 'number' => null, 'title' => $title, 'page' => $page, 'indent' => $indent];
        }

        if (in_array(strtolower($title), self::NON_TOC_TITLES, true)) {
            return null;
        }

        return ['kind' => 'section', 'number' => null, 'title' => $title, 'page' => $page, 'indent' => $indent];
    }

    /**
     * @param  list<array{title: string, page: int, level: int, parent: int|null, indent: int}>  $sections
     * @return list<array{title: string, page: int, level: int, parent: int|null, indent: int}>
     */
    private function resolveSectionLevels(array $sections): array
    {
        if ($sections === []) {
            return $sections;
        }

        $minIndent = min(array_column($sections, 'indent'));
        $lastTop = null;

        foreach ($sections as $i => $section) {
            if ($section['indent'] >= $minIndent + 3) {
                $sections[$i]['level'] = 2;
                $sections[$i]['parent'] = $lastTop;
            } else {
                $sections[$i]['level'] = 1;
                $sections[$i]['parent'] = null;
                $lastTop = $i;
            }
        }

        return $sections;
    }
}
