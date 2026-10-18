<?php

namespace App\Services\Content;

/**
 * Deterministic page-text -> typed block segments for the book import.
 *
 * Pure (no DB): takes cleaned PDF page texts with provenance stamps and
 * returns a flat list of segments (heading/paragraph/bullets/callout/
 * code/output) that the BookLessonBuilder turns into lesson blocks.
 * Everything here is derived from the book text itself - no AI involved.
 */
final class BookTextSegmenter
{
    /**
     * Strip running headers, footers, folios and copyright/DOI noise from
     * each page and repair line-level artifacts (drop caps, floating
     * soft-hyphen fragments). Idempotent: safe to run on cleaned text.
     *
     * @param  list<array{pdf: int, printed: int, text: string}>  $pages
     * @return list<array{pdf: int, printed: int, text: string}>
     */
    public function clean(array $pages): array
    {
        $out = [];

        foreach ($pages as $page) {
            $entries = [];
            foreach ($this->stripPageNoise($page['text']) as $line) {
                $entries[] = ['t' => $line, 'pdf' => $page['pdf'], 'pr' => $page['printed']];
            }

            $entries = $this->repairDropCaps($entries);
            $kept = [];
            foreach ($entries as $e) {
                if ($e['t'] !== null) {
                    $kept[] = $e['t'];
                }
            }

            $out[] = ['pdf' => $page['pdf'], 'printed' => $page['printed'], 'text' => implode("\n", $kept)];
        }

        return $out;
    }

    /**
     * @param  list<array{pdf: int, printed: int, text: string}>  $pages
     * @param  array{chapter_number: int, chapter_title: string, section_title: string, is_chapter_start: bool}  $context
     * @return list<array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}>
     */
    public function segment(array $pages, array $context): array
    {
        $lines = $this->flatten($pages);
        $lines = $this->repairDropCaps($lines);

        if ($lines === []) {
            return [];
        }

        return $this->run($this->splitLeadHeadings($this->chunk($lines), $context), $context);
    }

    /**
     * @return list<string>
     */
    private function stripPageNoise(string $text): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $rawLines = array_map(
            static fn (string $l): string => rtrim($l, " \t"),
            $rawLines,
        );

        // Drop running header (first non-blank line of the page).
        $start = 0;
        foreach ($rawLines as $i => $l) {
            if (trim($l) === '') {
                continue;
            }
            if (preg_match('/^(?:Chapter\s+\d+\s+\S|PHP 8 Objects|M\. Zandstra|Objects, Patterns)/iu', trim($l)) === 1) {
                $start = $i + 1;
            }

            break;
        }

        // Drop footer block at page end: folio + copyright + DOI + trailing blanks.
        $end = count($rawLines);
        while ($end > $start) {
            $t = trim($rawLines[$end - 1]);
            if ($t === ''
                || preg_match('/^\d{1,3}$/u', $t) === 1
                || str_contains($t, 'Matt Zandstra 2021')
                || str_starts_with($t, 'M. Zandstra,')
                || str_contains($t, 'doi.org/')
                || str_starts_with($t, 'Apress')
                || preg_match('/^www\.apress\.com/u', $t) === 1
            ) {
                $end--;

                continue;
            }

            break;
        }

        $out = [];
        for ($i = $start; $i < $end; $i++) {
            $t = trim($rawLines[$i]);

            if ($t !== ''
                && (str_contains($t, 'Matt Zandstra 2021')
                    || str_starts_with($t, 'M. Zandstra,')
                    || str_contains($t, 'doi.org/'))
            ) {
                continue;
            }

            $out[] = $rawLines[$i];
        }

        return $out;
    }

    /**
     * @param  list<array{pdf: int, printed: int, text: string}>  $pages
     * @return list<array{t: string|null, pdf: int, pr: int}>
     */
    private function flatten(array $pages): array
    {
        $out = [];
        $prevKept = null;

        foreach ($pages as $page) {
            $noise = $this->stripPageNoise($page['text']);

            // Page-leading blanks separate a new paragraph from the previous
            // page's text - unless the previous page ended mid-sentence,
            // in which case the paragraph simply continues flush.
            if ($prevKept !== null
                && preg_match('/[.!?;:"\'”\)]\s*$/u', $prevKept) !== 1
            ) {
                while ($noise !== [] && trim($noise[0]) === '') {
                    array_shift($noise);
                }
            }

            foreach ($noise as $line) {
                $out[] = ['t' => $line, 'pdf' => $page['pdf'], 'pr' => $page['printed']];
                if (trim($line) !== '') {
                    $prevKept = $line;
                }
            }
        }

        return $out;
    }

    /**
     * Repair line-level extraction artifacts in place:
     *  - floating soft-hyphen fragments ("­ bject-­")
     *  - lone capital letter lines (dropped caps: "C" + "lasses ...")
     *
     * @param  list<array{t: string|null, pdf: int, pr: int}>  $lines
     * @return list<array{t: string|null, pdf: int, pr: int}>
     */
    private function repairDropCaps(array $lines): array
    {
        $n = count($lines);

        // "T he Problem" letter-space splits (heading drop-cap artifact).
        for ($i = 0; $i < $n; $i++) {
            $t = $lines[$i]['t'] ?? null;
            if ($t !== null && preg_match('/^(\s*)T he(?=\s|$)/u', $t) === 1) {
                $el = $lines[$i];
                $el['t'] = preg_replace('/^(\s*)T he(?=\s|$)/u', '$1The', $t) ?? $t;
                $lines[$i] = $el;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $t = $lines[$i]['t'] ?? null;
            if ($t === null || preg_match('/^\s*\x{00AD}/u', $t) !== 1) {
                continue;
            }

            $tr = trim($t);
            $body = preg_replace('/^\x{00AD}\s*/u', '', $tr) ?? '';

            if (mb_strlen($tr) <= 20) {
                // Floating piece: belongs at the end of the next line.
                $j = $i + 1;
                while ($j < $n && ($lines[$j]['t'] === null || trim((string) $lines[$j]['t']) === '')) {
                    $j++;
                }
                if ($j < $n) {
                    $el = $lines[$j];
                    $el['t'] = rtrim((string) $el['t']).$body;
                    $lines[$j] = $el;
                }
                $el = $lines[$i];
                $el['t'] = null;
                $lines[$i] = $el;
            } else {
                // Full line with a junk soft-hyphen prefix.
                $el = $lines[$i];
                $el['t'] = preg_replace('/^\s*\x{00AD}/u', '', $t) ?? $t;
                $lines[$i] = $el;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $t = $lines[$i]['t'] ?? null;
            if ($t === null || preg_match('/^[A-Z]$/u', trim($t)) !== 1) {
                continue;
            }

            $letter = trim($t);

            // Next line starts lowercase: the letter begins that line's word.
            $j = $i + 1;
            while ($j < $n && ($lines[$j]['t'] === null || trim((string) $lines[$j]['t']) === '')) {
                $j++;
            }
            if ($j < $n && preg_match('/^[a-z]/u', trim((string) $lines[$j]['t'])) === 1) {
                $el = $lines[$j];
                $el['t'] = $letter.ltrim((string) $el['t']);
                $lines[$j] = $el;
                $el = $lines[$i];
                $el['t'] = null;
                $lines[$i] = $el;

                continue;
            }

            // Previous line starts lowercase: the floating letter begins it.
            $k = $i - 1;
            while ($k >= 0 && ($lines[$k]['t'] === null || trim((string) $lines[$k]['t']) === '')) {
                $k--;
            }
            if ($k >= 0) {
                $prev = (string) $lines[$k]['t'];
                if (preg_match('/^[a-z]/u', ltrim($prev)) === 1) {
                    $el = $lines[$k];
                    $el['t'] = $letter.ltrim($prev);
                    $lines[$k] = $el;
                    $el = $lines[$i];
                    $el['t'] = null;
                    $lines[$i] = $el;

                    continue;
                }
                if (preg_match('/[a-z]$/u', rtrim($prev)) === 1) {
                    $el = $lines[$k];
                    $el['t'] = rtrim($prev).$letter;
                    $lines[$k] = $el;
                    $el = $lines[$i];
                    $el['t'] = null;
                    $lines[$i] = $el;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{t: string|null, pdf: int, pr: int}>  $lines
     * @return list<array{lines: list<string>, pdf_from: int, pdf_to: int, pr_from: int, pr_to: int}>
     */
    private function chunk(array $lines): array
    {
        $chunks = [];
        $cur = [];
        $pdfFrom = 0;
        $pdfTo = 0;
        $prFrom = 0;
        $prTo = 0;

        foreach ($lines as $line) {
            if ($line['t'] === null) {
                continue;
            }

            if ($cur === []) {
                if (trim($line['t']) === '') {
                    continue;
                }

                $pdfFrom = $line['pdf'];
                $prFrom = $line['pr'];
            }

            if (trim($line['t']) === '') {
                if ($cur !== []) {
                    $chunks[] = [
                        'lines' => $cur,
                        'pdf_from' => $pdfFrom,
                        'pdf_to' => $pdfTo,
                        'pr_from' => $prFrom,
                        'pr_to' => $prTo,
                    ];
                    $cur = [];
                }

                continue;
            }

            if ($cur !== [] && $this->startsIndentedParagraph($cur, $line['t'])) {
                $chunks[] = [
                    'lines' => $cur,
                    'pdf_from' => $pdfFrom,
                    'pdf_to' => $pdfTo,
                    'pr_from' => $prFrom,
                    'pr_to' => $prTo,
                ];
                $cur = [];
                $pdfFrom = $line['pdf'];
                $prFrom = $line['pr'];
            }

            $cur[] = $line['t'];
            $pdfTo = $line['pdf'];
            $prTo = $line['pr'];
        }

        if ($cur !== []) {
            $chunks[] = [
                'lines' => $cur,
                'pdf_from' => $pdfFrom,
                'pdf_to' => $pdfTo,
                'pr_from' => $prFrom,
                'pr_to' => $prTo,
            ];
        }

        return $chunks;
    }

    /**
     * The book starts a new paragraph with a first-line indent when the
     * extraction dropped the blank line between paragraphs. Split only on a
     * long sentence-ending line followed by an indented line, so continuation
     * wraps and indented code never break apart.
     *
     * @param  list<string>  $cur
     */
    private function startsIndentedParagraph(array $cur, string $raw): bool
    {
        $lead = strlen($raw) - strlen(ltrim($raw, " \t"));

        if ($lead < 2) {
            return false;
        }

        $prev = trim($cur[count($cur) - 1] ?? '');

        if (mb_strlen($prev) < 40 || preg_match('/[.!?”"\')\]]$/u', $prev) !== 1) {
            return false;
        }

        $first = $cur[0] ?? '';

        return ! $this->looksCode($cur, $first !== '' && ($first[0] === ' ' || $first[0] === "\t"));
    }

    /**
     * Extraction sometimes omits the blank line after a heading; split a
     * leading flush heading line off its paragraph. Also strips a chapter
     * title glued to the intro paragraph's first line.
     *
     * @param  list<array{lines: list<string>, pdf_from: int, pdf_to: int, pr_from: int, pr_to: int}>  $chunks
     * @param  array{chapter_number: int, chapter_title: string, section_title: string, is_chapter_start: bool}  $context
     * @return list<array{lines: list<string>, pdf_from: int, pdf_to: int, pr_from: int, pr_to: int}>
     */
    private function splitLeadHeadings(array $chunks, array $context): array
    {
        if ($context['is_chapter_start']) {
            $title = $context['chapter_title'];
            $titleNorm = mb_strtolower(preg_replace('/\s+/u', ' ', trim($title)) ?? '');

            foreach ($chunks as $ci => $chunk) {
                $lines = $chunk['lines'];
                $n = count($lines);
                if ($n === 0) {
                    continue;
                }

                // The chapter title may sit on its own line(s), possibly glued
                // to the intro's first line, possibly after a "CHAPTER N"
                // marker line in the same chunk.
                $replaced = false;
                for ($s = 0; $s <= 1 && $s < $n; $s++) {
                    $maxK = min($n - 1, $s + 2);
                    for ($k = $s; $k <= $maxK; $k++) {
                        $joined = preg_replace('/\s+/u', ' ', trim(implode(' ', array_slice($lines, $s, $k - $s + 1)))) ?? '';
                        $joinedNorm = mb_strtolower($joined);

                        if ($joinedNorm === $titleNorm) {
                            $chunks[$ci]['lines'] = array_merge(
                                array_slice($lines, 0, $s),
                                array_slice($lines, $k + 1),
                            );
                            $replaced = true;
                            break 2;
                        }
                        if (str_starts_with($joinedNorm, $titleNorm.' ')) {
                            $remainder = trim(mb_substr($joined, mb_strlen($titleNorm) + 1));
                            $chunks[$ci]['lines'] = array_merge(
                                array_slice($lines, 0, $s),
                                $remainder === '' ? [] : [$remainder],
                                array_slice($lines, $k + 1),
                            );
                            $replaced = true;
                            break 2;
                        }
                    }
                }

                if ($replaced) {
                    break;
                }
            }

            $chunks = array_values(array_filter($chunks, fn (array $c): bool => $c['lines'] !== []));
        }

        $out = [];

        foreach ($chunks as $chunk) {
            $lines = $chunk['lines'];

            // Bullet run interrupted by a flush heading line (no blank line
            // before it): split the heading off into its own chunk.
            if (count($lines) >= 2 && str_starts_with(trim($lines[0]), '•')) {
                $splitAt = null;
                foreach ($lines as $k => $line) {
                    if ($k === 0) {
                        continue;
                    }
                    $t = trim($line);
                    if ($t === '' || str_starts_with($t, '•')) {
                        continue;
                    }
                    if ($line[0] !== ' ' && $line[0] !== "\t"
                        && $this->isHeadingish($t)
                        && ! str_ends_with(trim($lines[$k - 1]), ':')
                    ) {
                        $splitAt = $k;

                        break;
                    }
                }

                if ($splitAt !== null) {
                    $out[] = [
                        'lines' => array_slice($lines, 0, $splitAt),
                        'pdf_from' => $chunk['pdf_from'],
                        'pdf_to' => $chunk['pdf_to'],
                        'pr_from' => $chunk['pr_from'],
                        'pr_to' => $chunk['pr_to'],
                    ];
                    $out[] = [
                        'lines' => array_slice($lines, $splitAt),
                        'pdf_from' => $chunk['pdf_from'],
                        'pdf_to' => $chunk['pdf_to'],
                        'pr_from' => $chunk['pr_from'],
                        'pr_to' => $chunk['pr_to'],
                    ];

                    continue;
                }
            }

            if (count($lines) >= 2) {
                $l0 = trim($lines[0]);
                $l1 = trim($lines[1]);
                $l1Indented = $lines[1] !== '' && ($lines[1][0] === ' ' || $lines[1][0] === "\t");

                if ($this->leadIsHeading($l0, $lines[0])
                    && ! $this->isHeadingish($l1)
                    && ($l1Indented
                        || mb_strlen($l1) >= 60
                        || preg_match('/^(?:The|A|An|In|If|You|It|This|There|PHP|We|I |Each|For |When|After|Before|Because|Although|By |In )\b/u', $l1) === 1)
                ) {
                    $out[] = [
                        'lines' => [$lines[0]],
                        'pdf_from' => $chunk['pdf_from'],
                        'pdf_to' => $chunk['pdf_from'],
                        'pr_from' => $chunk['pr_from'],
                        'pr_to' => $chunk['pr_from'],
                    ];
                    $rest = array_slice($lines, 1);
                    $out[] = [
                        'lines' => $rest,
                        'pdf_from' => $chunk['pdf_from'],
                        'pdf_to' => $chunk['pdf_to'],
                        'pr_from' => $chunk['pr_from'],
                        'pr_to' => $chunk['pr_to'],
                    ];

                    continue;
                }
            }

            $out[] = $chunk;
        }

        return $out;
    }

    private function leadIsHeading(string $trimmed, string $raw): bool
    {
        if ($raw !== '' && ($raw[0] === ' ' || $raw[0] === "\t")) {
            return false;
        }

        return $this->isHeadingish($trimmed);
    }

    /**
     * State machine over blank-separated chunks.
     *
     * @param  list<array{lines: list<string>, pdf_from: int, pdf_to: int, pr_from: int, pr_to: int}>  $chunks
     * @param  array{chapter_number: int, chapter_title: string, section_title: string, is_chapter_start: bool}  $context
     * @return list<array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}>
     */
    private function run(array $chunks, array $context): array
    {
        $seg = [];

        $openType = null;
        $openLines = [];
        $openItems = [];
        $openRef = null;
        $openPdfFrom = 0;
        $openPdfTo = 0;
        $openPrFrom = 0;
        $openPrTo = 0;

        $pendingRef = null;        // listing marker seen, awaiting code chunk
        $outputIntro = false;      // previous paragraph announced program output
        $skipNextTitle = false;    // chapter marker seen, title chunk follows
        $chapterHeadingDone = ! $context['is_chapter_start'];

        $count = count($chunks);

        for ($ci = 0; $ci < $count; $ci++) {
            $chunk = $chunks[$ci];
            $rawFirst = $chunk['lines'][0];
            $first = trim($rawFirst);
            $indented = $rawFirst !== '' && ($rawFirst[0] === ' ' || $rawFirst[0] === "\t");

            $next = $chunks[$ci + 1] ?? null;
            $nextFirst = $next !== null && $next['lines'] !== [] ? trim($next['lines'][0]) : null;

            // --- Listing marker chunk: remember ref, consume chunk. ---
            if (preg_match('/^\/\/\s*listing\s+([\d.]+)$/iu', $first, $m) === 1 && count($chunk['lines']) === 1) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $pendingRef = $m[1];
                $outputIntro = false;

                continue;
            }

            // --- Chapter opener marker: "CHAPTER 3" -> heading. ---
            if (! $chapterHeadingDone && preg_match('/^CHAPTER\s+(\d+)$/iu', $first, $m) === 1) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $seg[] = [
                    'type' => 'heading',
                    'text' => 'Chapter '.$m[1].' - '.$context['chapter_title'],
                    'items' => [],
                    'lang' => '',
                    'listing_ref' => null,
                    'page_pdf_from' => $chunk['pdf_from'],
                    'page_pdf_to' => $chunk['pdf_to'],
                    'page_printed_from' => $chunk['pr_from'],
                    'page_printed_to' => $chunk['pr_to'],
                ];
                $chapterHeadingDone = true;
                $skipNextTitle = true;
                $pendingRef = null;
                $outputIntro = false;

                if (count($chunk['lines']) >= 2) {
                    if ($this->sameHeading($chunk['lines'][1], $context['chapter_title'])) {
                        $skipNextTitle = false;
                    } else {
                        array_splice($chunks, $ci + 1, 0, [[
                            'lines' => array_slice($chunk['lines'], 1),
                            'pdf_from' => $chunk['pdf_from'],
                            'pdf_to' => $chunk['pdf_to'],
                            'pr_from' => $chunk['pr_from'],
                            'pr_to' => $chunk['pr_to'],
                        ]]);
                        $count++;
                    }
                }

                continue;
            }

            // --- Chapter title line right after the marker: skip it. ---
            if ($skipNextTitle) {
                $skipNextTitle = false;

                if (count($chunk['lines']) === 1 && $this->sameHeading($first, $context['chapter_title'])) {
                    $pendingRef = null;
                    $outputIntro = false;

                    continue;
                }
            }

            // --- Append to open code when the chunk still looks like code. ---
            if ($openType === 'code' && $this->looksCode($chunk['lines'], $indented)) {
                $openLines[] = '';
                $openLines = array_merge($openLines, $chunk['lines']);
                $openPdfTo = $chunk['pdf_to'];
                $openPrTo = $chunk['pr_to'];
                $pendingRef = null;
                $outputIntro = false;

                continue;
            }

            // --- Code immediately followed by its output (no intro line). ---
            if ($openType === 'code'
                && $this->looksOutput($chunk['lines'], $indented)
                && ! $this->looksCode($chunk['lines'], $indented)
            ) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $openType = 'output';
                $openLines = $chunk['lines'];
                $openPdfFrom = $chunk['pdf_from'];
                $openPdfTo = $chunk['pdf_to'];
                $openPrFrom = $chunk['pr_from'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                continue;
            }

            // --- Append to open output while lines keep looking like output. ---
            if ($openType === 'output' && $this->looksOutput($chunk['lines'], $indented)) {
                $openLines[] = '';
                $openLines = array_merge($openLines, $chunk['lines']);
                $openPdfTo = $chunk['pdf_to'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                continue;
            }

            // --- Start a code buffer: listing marker seen earlier, or an
            // inline marker on the chunk itself (markers beat everything). ---
            $inlineRef = null;
            $isCodeStart = false;

            if ($pendingRef !== null) {
                $isCodeStart = $this->looksCode($chunk['lines'], $indented);
                if ($isCodeStart) {
                    $inlineRef = $pendingRef;
                }
                $pendingRef = null;
            } elseif (preg_match('/^\/\/\s*listing\s+([\d.]+)(?:\s|$)/iu', $first, $m) === 1) {
                $isCodeStart = true;
                $inlineRef = $m[1];
            }

            if ($isCodeStart) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $lines = $chunk['lines'];
                if (preg_match('/^\/\/\s*listing\s+[\d.]+(?:\s.*)?$/iu', trim($lines[0])) === 1) {
                    array_shift($lines);
                }
                if ($lines === []) {
                    continue;
                }
                $openType = 'code';
                $openLines = $lines;
                $openRef = $inlineRef;
                $openPdfFrom = $chunk['pdf_from'];
                $openPdfTo = $chunk['pdf_to'];
                $openPrFrom = $chunk['pr_from'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                continue;
            }

            // --- Output announced by the previous paragraph (intro wins over
            // generic code detection: var_dump blocks look like code too). ---
            if ($outputIntro && (! $indented || $this->looksCode($chunk['lines'], $indented))) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $openType = 'output';
                $openLines = $chunk['lines'];
                $openPdfFrom = $chunk['pdf_from'];
                $openPdfTo = $chunk['pdf_to'];
                $openPrFrom = $chunk['pr_from'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                continue;
            }

            // --- Generic code snippet (strong signals, e.g. "like this:" intro). ---
            if ($this->looksCode($chunk['lines'], $indented) && (! $indented || $this->strongCode($first))) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $openType = 'code';
                $openLines = $chunk['lines'];
                $openRef = null;
                $openPdfFrom = $chunk['pdf_from'];
                $openPdfTo = $chunk['pdf_to'];
                $openPrFrom = $chunk['pr_from'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                continue;
            }

            // --- Bullet run (starts or continues an open bullet block). ---
            if (str_starts_with($first, '•')) {
                if ($openType !== 'bullets') {
                    $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                    $openType = 'bullets';
                    $openItems = [];
                    $openPdfFrom = $chunk['pdf_from'];
                    $openPrFrom = $chunk['pr_from'];
                }
                $openPdfTo = $chunk['pdf_to'];
                $openPrTo = $chunk['pr_to'];
                $outputIntro = false;

                $this->appendBulletLines($chunk['lines'], $openItems);

                continue;
            }

            // --- Bullets continue with hanging-indent continuation lines. ---
            if ($openType === 'bullets') {
                $words = count(preg_split('/\s+/u', $this->joinProse($chunk['lines'])) ?: []);
                $continuation = ! $this->isHeadingLine($chunk, $nextFirst)
                    && ! $this->looksCode($chunk['lines'], $indented)
                    && (! $indented || $words <= 14);

                if ($continuation) {
                    if ($openItems !== []) {
                        $openItems[count($openItems) - 1] = $this->joinProse([
                            $openItems[count($openItems) - 1],
                            $this->joinProse($chunk['lines']),
                        ]);
                    }
                    $openPdfTo = $chunk['pdf_to'];
                    $openPrTo = $chunk['pr_to'];

                    continue;
                }

                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
            }

            // --- Note callout (first line "Note ..."). ---
            if (preg_match('/^Note\s+\S/u', $first) === 1 && ! $this->looksCode($chunk['lines'], $indented)) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $seg[] = [
                    'type' => 'callout',
                    'text' => $this->joinProse($chunk['lines']),
                    'items' => [],
                    'lang' => '',
                    'listing_ref' => null,
                    'page_pdf_from' => $chunk['pdf_from'],
                    'page_pdf_to' => $chunk['pdf_to'],
                    'page_printed_from' => $chunk['pr_from'],
                    'page_printed_to' => $chunk['pr_to'],
                ];
                $outputIntro = false;

                continue;
            }

            // --- Standalone heading. ---
            if ($this->isHeadingLine($chunk, $nextFirst)) {
                $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
                $seg[] = [
                    'type' => 'heading',
                    'text' => $first,
                    'items' => [],
                    'lang' => '',
                    'listing_ref' => null,
                    'page_pdf_from' => $chunk['pdf_from'],
                    'page_pdf_to' => $chunk['pdf_to'],
                    'page_printed_from' => $chunk['pr_from'],
                    'page_printed_to' => $chunk['pr_to'],
                ];
                $pendingRef = null;
                $outputIntro = false;

                continue;
            }

            // --- Default: prose paragraph. ---
            $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);
            $openType = 'paragraph';
            $openLines = $chunk['lines'];
            $openRef = null;
            $openPdfFrom = $chunk['pdf_from'];
            $openPdfTo = $chunk['pdf_to'];
            $openPrFrom = $chunk['pr_from'];
            $openPrTo = $chunk['pr_to'];
            $pendingRef = null;

            $joined = $this->joinProse($chunk['lines']);
            $outputIntro = $this->announcesOutput($joined);
        }

        $this->flushBuffer($openType, $openLines, $openItems, $openRef, $openPdfFrom, $openPdfTo, $openPrFrom, $openPrTo, $seg);

        return $seg;
    }

    /**
     * Flush the open buffer (paragraph/code/output/bullets) into the segment
     * list. Resets the buffer.
     *
     * @param  list<string>  $openLines
     * @param  list<string>  $openItems
     * @param  list<array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}>  $seg
     *
     * @param-out null  $openType
     * @param-out null  $openRef
     */
    private function flushBuffer(
        ?string &$openType,
        array &$openLines,
        array &$openItems,
        ?string &$openRef,
        int &$openPdfFrom,
        int &$openPdfTo,
        int &$openPrFrom,
        int &$openPrTo,
        array &$seg,
    ): void {
        if ($openType === null) {
            return;
        }

        $text = match ($openType) {
            'bullets' => '',
            'code', 'output' => rtrim(implode("\n", $openLines)),
            default => $this->joinProse($openLines),
        };

        $keep = $openType === 'bullets'
            ? $openItems !== []
            : $text !== '';

        if ($keep) {
            $seg[] = [
                'type' => $openType,
                'text' => $text,
                'items' => $openType === 'bullets' ? $openItems : [],
                'lang' => $openType === 'code' ? 'php' : '',
                'listing_ref' => $openRef,
                'page_pdf_from' => $openPdfFrom,
                'page_pdf_to' => $openPdfTo,
                'page_printed_from' => $openPrFrom,
                'page_printed_to' => $openPrTo,
            ];
        }

        $openType = null;
        $openLines = [];
        $openItems = [];
        $openRef = null;
    }

    /**
     * Parse bullet chunk lines into item strings (continuation lines join
     * the previous item). A flush heading-like line ends the bullet run and
     * is requeued as its own chunk.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $items
     */
    private function appendBulletLines(array $lines, array &$items): void
    {
        foreach ($lines as $line) {
            $t = trim($line);

            if (str_starts_with($t, '•')) {
                $items[] = preg_replace('/^•\s*/u', '', $t) ?: $t;

                continue;
            }

            if ($items !== []) {
                $items[count($items) - 1] = $this->joinProse([$items[count($items) - 1], $t]);
            }
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function joinProse(array $lines): string
    {
        $out = '';

        foreach ($lines as $i => $line) {
            $t = $i === 0 ? ltrim($line) : trim($line);
            $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

            if ($out === '') {
                $out = $t;

                continue;
            }

            if (preg_match('/\x{00AD}$/u', $out) === 1) {
                $out = (preg_replace('/\x{00AD}$/u', '', $out) ?? $out).$t;
            } elseif (str_ends_with($out, '-')) {
                $out .= $t;
            } else {
                $out .= ' '.$t;
            }
        }

        return trim(str_replace("\u{00AD}", '', $out));
    }

    /**
     * @param  list<string>  $lines
     */
    private function looksCode(array $lines, bool $indentedFirst): bool
    {
        $checked = 0;
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }

            if ($this->strongCode($t)) {
                return true;
            }

            if (++$checked >= 4) {
                break;
            }
        }

        if (! $indentedFirst) {
            foreach (array_slice($lines, 0, 4) as $line) {
                if (preg_match('/[;{}]|->|::|=>|\$\w/u', $line) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function strongCode(string $t): bool
    {
        if (str_starts_with($t, '<?php') || str_starts_with($t, '<?=')
            || str_starts_with($t, '//') || str_starts_with($t, '#')
            || str_starts_with($t, '$') || str_starts_with($t, '}')
            || str_starts_with($t, '{') || str_starts_with($t, ')')
            || str_starts_with($t, '(') || str_starts_with($t, '\'')
            || str_starts_with($t, '"') || str_starts_with($t, '=')
            || str_starts_with($t, 'return ') || str_starts_with($t, 'echo ')
            || str_starts_with($t, 'print ') || str_starts_with($t, 'new ')
            || str_starts_with($t, 'function ') || str_starts_with($t, 'class ')
            || str_starts_with($t, 'abstract ') || str_starts_with($t, 'final ')
            || str_starts_with($t, 'interface ') || str_starts_with($t, 'trait ')
            || str_starts_with($t, 'use ') || str_starts_with($t, 'namespace ')
            || str_starts_with($t, 'public ') || str_starts_with($t, 'private ')
            || str_starts_with($t, 'protected ') || str_starts_with($t, 'static ')
            || str_starts_with($t, 'const ') || str_starts_with($t, 'if (')
            || str_starts_with($t, 'if(') || str_starts_with($t, 'foreach ')
            || str_starts_with($t, 'while ') || str_starts_with($t, 'for (')
            || str_starts_with($t, 'switch ') || str_starts_with($t, 'try {')
            || str_starts_with($t, 'catch ') || str_starts_with($t, 'throw ')
            || str_starts_with($t, 'declare(') || str_starts_with($t, 'require')
            || str_starts_with($t, 'include') || str_starts_with($t, 'match (')
        ) {
            return true;
        }

        return preg_match('/(?:\$\w|->|::|=>|\bfunction\b|\bpublic\b|\breturn\b)/u', $t) === 1
            && preg_match('/[;{}]/u', $t) === 1;
    }

    /**
     * @param  list<string>  $lines
     */
    private function looksOutput(array $lines, bool $indentedFirst): bool
    {
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }

            if ($this->strongOutput($t) || $this->strongCode($t)) {
                return true;
            }

            break;
        }

        return ! $indentedFirst && $this->isHeadingish(trim($lines[0] ?? ''));
    }

    private function strongOutput(string $t): bool
    {
        return preg_match(
            '/^(?:object\s*\(|\s*stdClass\b|int\(|integer\(|string\(|bool\(|boolean\(|float\(|double\(|array\(|NULL\b|resource\(|unknown type|number\()/iu',
            $t,
        ) === 1
            || str_starts_with($t, '}')
            || str_starts_with($t, '"')
            || str_starts_with($t, '\'')
            || str_contains($t, ' => ')
            || str_contains($t, ']=>');
    }

    /**
     * @param  array{lines: list<string>, pdf_from: int, pdf_to: int, pr_from: int, pr_to: int}  $chunk
     */
    private function isHeadingLine(array $chunk, ?string $nextFirst): bool
    {
        $lines = $chunk['lines'];

        if (count($lines) !== 1) {
            return false;
        }

        $t = trim($lines[0]);

        if ($t === '' || mb_strlen($t) > 80
            || str_ends_with($t, ':') || str_ends_with($t, '.')
            || str_ends_with($t, ';') || str_ends_with($t, ',')
        ) {
            return false;
        }

        if ($this->strongCode($t) || $this->strongOutput($t) || preg_match('/[=$;{}<>]/u', $t) === 1) {
            return false;
        }

        $words = preg_split('/\s+/u', $t) ?: [];
        if (count($words) > 10) {
            return false;
        }

        if ($nextFirst === null) {
            return mb_strlen($t) <= 60;
        }

        if ($nextFirst === '') {
            return false;
        }

        return ($nextFirst[0] ?? '') === ' '
            || $this->strongCode($nextFirst)
            || preg_match('/^\/\/\s*listing/iu', $nextFirst) === 1
            || preg_match('/^CHAPTER\s+\d+$/iu', $nextFirst) === 1
            || str_starts_with($nextFirst, '•')
            || preg_match('/[.!?;:]\s*$/u', $nextFirst) === 1
            || (mb_strlen($nextFirst) >= 40 && ! $this->isHeadingish($nextFirst));
    }

    private function isHeadingish(string $t): bool
    {
        if ($t === '' || mb_strlen($t) > 80) {
            return false;
        }

        if (str_ends_with($t, '.') || str_ends_with($t, ';') || str_ends_with($t, ':') || str_ends_with($t, ',')) {
            return false;
        }

        if ($this->strongCode($t) || $this->strongOutput($t)) {
            return false;
        }

        $words = preg_split('/\s+/u', $t) ?: [];

        return count($words) <= 10;
    }

    private function sameHeading(string $a, string $b): bool
    {
        $norm = static fn (string $s): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s));

        return $norm($a) === $norm($b);
    }

    private function announcesOutput(string $text): bool
    {
        $t = rtrim($text);

        if (! str_ends_with($t, ':')) {
            return false;
        }

        $tail = mb_substr($t, -120);

        return preg_match(
            '/(?:following\s+(?:output|result)|produces?\s+(?:the\s+)?following|prints?\s+(?:the\s+)?following|output\s*(?:of|:)|executing\s+[^:]{0,70}(?:output|screen)|produces?:|displays?:|screen\s+output|yields?:|shows?:)/iu',
            $tail,
        ) === 1;
    }
}
