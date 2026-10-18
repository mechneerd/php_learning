<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\ProvenanceSource;
use App\Models\Chapter;
use App\Models\CodeExample;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\ExerciseHint;
use App\Models\ExerciseTest;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Deterministic lesson builder: slices each lesson's section out of the
 * extracted book pages, runs it through the BookTextSegmenter, and
 * rewrites the lesson's blocks, listings, exercises, quiz and cards from
 * the original book text with full page provenance (no AI calls).
 */
final class BookLessonBuilder
{
    /** @var array<int, list<string>> cleaned page lines keyed by pdf page */
    private array $pageLines = [];

    /** @var array<int, int> printed page keyed by pdf page */
    private array $printed = [];

    /** @var array<string, int>|null token frequencies across the extracted pages */
    private ?array $tokenFreq = null;

    public function __construct(
        private readonly BookTextSegmenter $segmenter,
        private readonly BlockValidator $validator,
        private readonly FigureExtractor $figures,
    ) {}

    /**
     * Recompute every section's page range with a sequential cursor so
     * duplicate titles (e.g. repeated "The Problem") land on the right
     * occurrence. Idempotent; returns how many rows changed.
     *
     * @return array{checked: int, fixed: int, warnings: list<string>}
     */
    public function repairRanges(): array
    {
        $warnings = [];
        $checked = 0;
        $fixed = 0;

        $chapters = Chapter::query()->orderBy('ord')->get();
        $cursor = ['pdf' => 1, 'line' => 0];
        $pending = null;

        foreach ($chapters as $chapter) {
            $marker = $this->findChapterMarker($chapter, $cursor['pdf'], $cursor['line']);

            if ($marker === null) {
                $warnings[] = "{$chapter->title} marker not found from pdf {$cursor['pdf']}";
                $chapStart = ['pdf' => $cursor['pdf'], 'line' => 0, 'top' => true];
            } else {
                $chapStart = ['pdf' => $marker['pdf'], 'line' => $marker['line'], 'top' => $marker['top']];
            }

            $cursor = ['pdf' => $chapStart['pdf'], 'line' => $chapStart['line']];
            $sections = $chapter->sections()->orderBy('ord')->get();

            foreach ($sections as $i => $section) {
                $checked++;

                if ($i === 0) {
                    $startPdf = $chapStart['pdf'];
                    $startLine = $chapStart['line'];
                    $titleTop = $chapStart['top'];
                    $cursor = ['pdf' => $startPdf, 'line' => $startLine];
                } else {
                    $found = $this->findTitle((string) $section->title, $cursor['pdf'], $cursor['line']);

                    if ($found === null) {
                        $warnings[] = 'Section #'.$section->id.' "'.$section->title.'" not found from pdf '.$cursor['pdf'].'; range kept';

                        if ($pending !== null) {
                            $endPdf = (int) $section->page_pdf_from;

                            if ($endPdf > $pending['start'] && $endPdf <= $pending['start'] + 60) {
                                if ($this->writeRange($pending['section'], $pending['start'], $endPdf - 1)) {
                                    $fixed++;
                                }
                            }
                        }

                        $pending = null;

                        // Keep the cursor: the stale page_pdf_to may point
                        // backwards (duplicate-title chapters) and would break
                        // every later search.
                        continue;
                    }

                    $startPdf = $found['pdf'];
                    $startLine = $found['line'] + $found['consumed'];
                    $titleTop = $found['top'];
                    $cursor = ['pdf' => $startPdf, 'line' => $startLine];
                }

                if ($pending !== null) {
                    $endPdf = $titleTop ? $startPdf - 1 : $startPdf;

                    if ($endPdf < $pending['start']) {
                        $endPdf = $pending['start'];
                    }

                    if ($this->writeRange($pending['section'], $pending['start'], $endPdf)) {
                        $fixed++;
                    }
                }

                $pending = ['section' => $section, 'start' => $startPdf];
            }
        }

        if ($pending !== null) {
            $last = (int) DB::table('pdf_pages')->max('page_pdf');
            $indexStart = $this->indexStartPage();

            if ($indexStart !== null) {
                $last = min($last, $indexStart - 1);
            }

            $endPdf = max($pending['start'], $last);

            if ($this->writeRange($pending['section'], $pending['start'], $endPdf)) {
                $fixed++;
            }
        }

        return ['checked' => $checked, 'fixed' => $fixed, 'warnings' => $warnings];
    }

    /**
     * Rebuild one lesson from its section's pages. Runs inside a
     * transaction; dry runs roll back and report the same stats.
     *
     * @return array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}
     */
    public function build(Lesson $lesson, bool $dryRun = false): array
    {
        $section = $lesson->section;
        $chapter = $lesson->chapter;

        if ($section === null || $chapter === null) {
            throw new RuntimeException("Lesson #{$lesson->id} has no section or chapter.");
        }

        if ($section->page_pdf_from === null || $section->page_pdf_to === null) {
            throw new RuntimeException("Section #{$section->id} has no page range.");
        }

        [$sliced, $context] = $this->slice($lesson, $section, $chapter);
        $segments = $this->segmenter->segment($sliced, $context);

        if ($segments === []) {
            throw new RuntimeException("No segments extracted for lesson #{$lesson->id}.");
        }

        DB::beginTransaction();

        try {
            $stats = $this->persist($lesson, $section, $segments);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $stats;
    }

    /**
     * @return array{0: list<array{pdf: int, printed: int, text: string}>, 1: array{chapter_number: int, chapter_title: string, section_title: string, is_chapter_start: bool}}
     */
    private function slice(Lesson $lesson, Section $section, Chapter $chapter): array
    {
        $from = (int) $section->page_pdf_from;
        $to = min((int) $section->page_pdf_to + 2, (int) DB::table('pdf_pages')->max('page_pdf'));

        $rows = DB::table('pdf_pages')
            ->whereBetween('page_pdf', [$from, $to])
            ->orderBy('page_pdf')
            ->get();

        if ($rows->isEmpty()) {
            throw new RuntimeException("No pdf pages loaded for section #{$section->id}.");
        }

        $pages = [];
        foreach ($rows as $row) {
            $pages[] = ['pdf' => (int) $row->page_pdf, 'printed' => (int) $row->page_printed, 'text' => (string) $row->text];
        }

        $pages = $this->segmenter->clean($pages);
        $isOpener = (int) $section->ord === 0;

        $startP = 0;
        $startL = 0;

        if ($isOpener) {
            $markerPattern = $this->markerPattern($chapter);
            $lines = preg_split('/\r\n|\r|\n/', $pages[0]['text']) ?: [];
            foreach ($lines as $li => $line) {
                if (preg_match($markerPattern, trim($line)) === 1) {
                    $startL = $li;

                    break;
                }
            }
        } else {
            $found = $this->findTitleIn($pages, (string) $section->title, 0, 0);

            if ($found === null) {
                throw new RuntimeException("Section title \"{$section->title}\" not found in its own range (lesson #{$lesson->id}).");
            }

            $startP = $found['p'];
            $startL = $found['line'] + $found['consumed'];
        }

        $nextChapter = Chapter::query()
            ->where('ord', '>', (int) $chapter->ord)
            ->orderBy('ord')
            ->first();
        $nextMarker = $nextChapter !== null ? $this->markerPattern($nextChapter) : null;
        $boundaryNorms = $this->boundaryTitles($section);

        $endP = count($pages) - 1;
        $endL = null;

        for ($pi = $startP; $pi < count($pages); $pi++) {
            $lines = preg_split('/\r\n|\r|\n/', $pages[$pi]['text']) ?: [];
            $li0 = $pi === $startP ? $startL : 0;

            for ($li = $li0; $li < count($lines); $li++) {
                $t = trim($lines[$li]);
                if ($t === '') {
                    continue;
                }

                if (($nextMarker !== null && preg_match($nextMarker, $t) === 1)
                    || preg_match('/^INDEX$/iu', $t) === 1) {
                    $endP = $pi;
                    $endL = $li;

                    break 2;
                }

                $matched = false;
                $joined = $this->norm($t);

                for ($w = 0; $w <= 2; $w++) {
                    if ($w > 0) {
                        if ($li + $w >= count($lines) || trim($lines[$li + $w]) === '') {
                            break;
                        }

                        $joined .= ' '.$this->norm(trim($lines[$li + $w]));
                    }

                    if (in_array($joined, $boundaryNorms, true)) {
                        $matched = true;

                        break;
                    }
                }

                if ($matched) {
                    $endP = $pi;
                    $endL = $li;

                    break 2;
                }
            }
        }

        $sliced = [];

        for ($pi = $startP; $pi <= $endP; $pi++) {
            $lines = preg_split('/\r\n|\r|\n/', $pages[$pi]['text']) ?: [];
            $from2 = $pi === $startP ? $startL : 0;
            $to2 = $pi === $endP && $endL !== null ? $endL - 1 : count($lines) - 1;
            $keep = array_slice($lines, $from2, max(0, $to2 - $from2 + 1));

            if ($keep !== []) {
                $sliced[] = ['pdf' => $pages[$pi]['pdf'], 'printed' => $pages[$pi]['printed'], 'text' => implode("\n", $keep)];
            }
        }

        if ($sliced === []) {
            throw new RuntimeException("Empty slice for lesson #{$lesson->id}.");
        }

        return [$sliced, [
            'chapter_number' => (int) $chapter->number,
            'chapter_title' => (string) $chapter->title,
            'section_title' => (string) $section->title,
            'is_chapter_start' => $isOpener,
        ]];
    }

    /**
     * Normalized titles of every section after this one (all levels), plus
     * the next chapter's first sections when the section ends a chapter.
     *
     * @return list<string>
     */
    private function boundaryTitles(Section $section): array
    {
        $sections = Section::query()
            ->where('chapter_id', $section->chapter_id)
            ->where('ord', '>', $section->ord)
            ->orderBy('ord')
            ->pluck('title');

        $titles = $sections->all();

        if ($titles === []) {
            $next = Section::query()
                ->whereHas('chapter', fn ($q) => $q->where('ord', '>', (int) DB::table('chapters')->where('id', $section->chapter_id)->value('ord')))
                ->orderBy('ord')
                ->limit(6)
                ->pluck('title');

            $titles = $next->all();
        }

        return array_values(array_unique(array_map($this->norm(...), $titles)));
    }

    /**
     * After a "Figure N-M" caption callout, attach the extracted raster
     * image for that figure when one exists in the book PDF. Silent no-op
     * for vector-only figures (no extractable image) or missing PDFs.
     *
     * @param  callable(string, array<string, mixed>, array{page_printed_from: int, page_printed_to: int, page_pdf_from: int, page_pdf_to: int}): void  $add
     * @param  array{page_printed_from: int, page_printed_to: int, page_pdf_from: int, page_pdf_to: int}  $stamp
     */
    private function addFigure(callable $add, string $captionText, array $stamp, Lesson $lesson): void
    {
        // Caption form only: "Figure 16-1. …" / "Figure 16-1: …" — prose
        // mentions ("…in Figure 16-2.") never start the text with a
        // punctuated reference, so they cannot double-insert the image.
        if (preg_match('/^Figure\s+(\d+-\d+)\s*[.:]/iu', trim($captionText), $m) !== 1) {
            return;
        }

        $figure = $this->figures->find($m[1], (int) $lesson->id);

        if ($figure === null) {
            return;
        }

        $add('image', [
            'figure_ref' => $m[1],
            'caption' => $figure['caption'],
            'page_pdf' => $figure['page_pdf'],
            'src' => $figure['src'],
            'alt' => $figure['alt'],
            'media_id' => $figure['media_id'],
            'width' => $figure['width'],
            'height' => $figure['height'],
        ], $stamp);
    }

    /**
     * @param  list<array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}>  $segments
     * @return array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}
     */
    private function persist(Lesson $lesson, Section $section, array $segments): array
    {
        $stats = ['blocks' => 0, 'code_examples' => 0, 'exercises' => 0, 'quiz' => 0, 'cards' => 0, 'concepts' => 0];

        $this->deleteExisting($lesson);

        $lesson->forceFill([
            'source' => ProvenanceSource::Book,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_prompt_version' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'is_outdated' => false,
            'status' => ContentStatus::InReview,
            'page_printed_from' => $section->page_printed_from,
            'page_printed_to' => $section->page_printed_to,
            'page_pdf_from' => $section->page_pdf_from,
            'page_pdf_to' => $section->page_pdf_to,
        ])->save();

        $blockRows = [];
        $ord = 0;
        $isSummary = str_starts_with(mb_strtolower($lesson->title), 'summary');
        $firstStamp = $this->stamp($segments[0]);
        $lastParagraph = null;
        $intro = null;
        $listings = [];
        $headingPairs = [];
        $codeHeadingAdded = false;

        $add = function (string $type, array $payload, array $stamp) use (&$blockRows, &$ord, &$stats): void {
            $blockType = BlockType::from($type);
            $validated = $this->validator->validate($blockType, $payload);

            if ($validated === []) {
                return;
            }

            $ord++;
            $blockRows[] = [
                'ord' => $ord,
                'type' => $blockType,
                'payload' => $validated,
                'source' => ProvenanceSource::Book,
                'page_printed_from' => $stamp['page_printed_from'],
                'page_printed_to' => $stamp['page_printed_to'],
                'page_pdf_from' => $stamp['page_pdf_from'],
                'page_pdf_to' => $stamp['page_pdf_to'],
            ];
            $stats['blocks']++;
        };

        if ($isSummary) {
            $add('heading', ['text' => '📌 Summary'], $firstStamp);
        } else {
            $add('heading', ['text' => '📖 Core explanation'], $firstStamp);
        }

        $count = count($segments);

        for ($i = 0; $i < $count; $i++) {
            $seg = $segments[$i];

            if ($seg['type'] === 'bullets') {
                $seg['items'] = array_map(fn (string $item): string => $this->repairDropCaps($item), $seg['items']);
            } elseif ($seg['type'] !== 'code' && $seg['type'] !== 'output') {
                $seg['text'] = $this->repairDropCaps($seg['text']);
            }

            $segments[$i] = $seg;
            $stamp = $this->stamp($seg);
            $next = $segments[$i + 1] ?? null;

            switch ($seg['type']) {
                case 'heading':
                    $disposition = $seg['text'] === '' ? 'paragraph' : $this->headingDisposition($seg['text']);

                    if ($disposition === 'callout') {
                        $add('callout', ['text' => $seg['text'], 'variant' => 'info'], $stamp);
                        $this->addFigure($add, $seg['text'], $stamp, $lesson);
                        $intro = null;
                    } elseif ($disposition === 'paragraph') {
                        $add('paragraph', ['markdown' => $seg['text']], $stamp);
                        $lastParagraph = $seg['text'];
                        $intro = $seg['text'];
                    } else {
                        $add('heading', ['text' => $seg['text']], $stamp);
                        $headingPairs[] = ['heading' => $seg['text'], 'stamp' => $stamp, 'next' => $next];
                        $intro = null;
                    }

                    break;

                case 'paragraph':
                    $add('paragraph', ['markdown' => $seg['text']], $stamp);
                    $this->addFigure($add, $seg['text'], $stamp, $lesson);
                    $lastParagraph = $seg['text'];
                    $intro = $seg['text'];

                    break;

                case 'bullets':
                    $add('bullets', ['items' => $seg['items']], $stamp);

                    break;

                case 'callout':
                    $add('callout', ['text' => $seg['text'], 'variant' => 'info'], $stamp);
                    $this->addFigure($add, $seg['text'], $stamp, $lesson);

                    break;

                case 'output':
                    if ($this->trustedOutput($seg['text']) === null) {
                        $add('callout', ['text' => $seg['text'], 'variant' => 'info'], $stamp);
                        $this->addFigure($add, $seg['text'], $stamp, $lesson);

                        break;
                    }

                    if (! $codeHeadingAdded) {
                        $add('heading', ['text' => '💻 Code examples'], $stamp);
                        $codeHeadingAdded = true;
                    }

                    $add('output', ['text' => $seg['text']], $stamp);

                    break;

                case 'code':
                    if (! $codeHeadingAdded) {
                        $add('heading', ['text' => '💻 Code examples'], $stamp);
                        $codeHeadingAdded = true;
                    }

                    $expectedOutput = $this->expectedOutputFollowing($segments, $i);

                    if ($seg['listing_ref'] !== null) {
                        $code = $seg['text'];

                        if (! str_contains($code, '<?php') && ! str_contains($code, '<?=')) {
                            $code = "<?php\n".$code;
                        }

                        $example = CodeExample::query()->create([
                            'lesson_id' => $lesson->id,
                            'concept_id' => null,
                            'listing_ref' => $seg['listing_ref'],
                            'tier' => 1,
                            'title' => $this->deriveTitle($intro, (string) $section->title),
                            'code' => $code,
                            'expected_output' => $expectedOutput,
                            'explanation' => $intro ?? 'From book listing '.$seg['listing_ref'].'.',
                            'syntax_notes' => null,
                            'common_mistake' => null,
                            'external_ref' => null,
                            'ord' => count($listings) + 1,
                            'status' => ContentStatus::InReview,
                            'source' => ProvenanceSource::Book,
                            'page_printed_from' => $stamp['page_printed_from'],
                            'page_printed_to' => $stamp['page_printed_to'],
                            'page_pdf_from' => $stamp['page_pdf_from'],
                            'page_pdf_to' => $stamp['page_pdf_to'],
                        ]);

                        $stats['code_examples']++;
                        $add('code_example', ['code_example_id' => $example->id], $stamp);

                        $listings[] = [
                            'ref' => $seg['listing_ref'],
                            'code' => $code,
                            'intro' => $intro,
                            'stamp' => $stamp,
                        ];
                    } else {
                        $add('code', ['lang' => 'php', 'code' => $seg['text']], $stamp);
                    }

                    break;
            }
        }

        $text = $lesson->title.' '.implode(' ', array_map(
            static fn (array $s): string => $s['text'].' '.implode(' ', $s['items']),
            $segments,
        ));

        $concepts = $this->linkConcepts($lesson, $text, $stats);

        if ($concepts !== []) {
            $stamp = $this->stamp($segments[$count - 1]);
            $add('heading', ['text' => '🧠 Key concepts'], $stamp);

            $items = [];
            foreach ($concepts as $concept) {
                $definition = $this->firstSentence((string) $concept->definition);
                $items[] = $definition !== '' ? "{$concept->name}: {$definition}" : (string) $concept->name;
            }

            $add('bullets', ['items' => array_slice($items, 0, 8)], $stamp);
        }

        $exerciseIds = $this->createExercises($lesson, $concepts, array_slice($listings, 0, 2), $lastParagraph, $stats);
        $exerciseLabels = $exerciseIds['labels'];
        $exerciseIds = $exerciseIds['ids'];

        $quizIds = $this->createQuiz($lesson, $concepts, $stats);
        $quizLabels = $quizIds['labels'];
        $quizIds = $quizIds['ids'];

        $cardLabels = $this->createCards($lesson, $concepts, $headingPairs, $stats);

        $stamp = $this->stamp($segments[$count - 1]);

        if ($exerciseIds !== []) {
            $add('heading', ['text' => '✍️ Exercises'], $stamp);
            $add('exercise_ref', ['labels' => $exerciseLabels, 'ids' => $exerciseIds], $stamp);
        }

        if ($quizIds !== []) {
            $add('heading', ['text' => '❓ Quiz'], $stamp);
            $add('quiz_ref', ['labels' => $quizLabels, 'ids' => $quizIds], $stamp);
        }

        if ($cardLabels !== []) {
            $add('card_refs', ['labels' => $cardLabels], $stamp);
        }

        foreach ($blockRows as $row) {
            LessonBlock::query()->create($row + ['lesson_id' => $lesson->id]);
        }

        $words = preg_split('/\s+/u', $text) ?: [];

        $lesson->forceFill([
            'est_minutes' => min(30, max(2, (int) ceil(count($words) / 150))),
        ])->save();

        return $stats;
    }

    /**
     * Frequency of one normalised token across the extracted book pages.
     */
    private function freq(string $token): int
    {
        if ($this->tokenFreq === null) {
            /** @var array<string, int> $freq */
            $freq = [];

            foreach (DB::table('pdf_pages')->pluck('text') as $page) {
                foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $page)) ?: [] as $word) {
                    if ($word !== '') {
                        $freq[$word] = ($freq[$word] ?? 0) + 1;
                    }
                }
            }

            $this->tokenFreq = $freq;
        }

        return $this->tokenFreq[$token] ?? 0;
    }

    /**
     * Repair same-line PDF drop-cap artefacts ("I nclude Paths" becomes
     * "Include Paths"). The extraction splits a display initial from the
     * rest of the word, so the fragment also appears as a standalone token
     * somewhere in the book; join it back only when the joined form
     * dominates the corpus, so real words after an article ("A common")
     * are never touched.
     */
    private function repairDropCaps(string $text): string
    {
        if (! preg_match('/\b[A-Z] [a-z]{3,}/u', $text)) {
            return $text;
        }

        return preg_replace_callback(
            '/\b([A-Z]) ([a-z]{3,})\b/u',
            function (array $matches): string {
                $letter = (string) $matches[1];
                $fragment = (string) $matches[2];
                $restFreq = $this->freq(mb_strtolower($fragment));
                $joinedFreq = $this->freq(mb_strtolower($letter.$fragment));

                if ($joinedFreq >= 3 && ($restFreq <= 2 || $joinedFreq >= 4 * $restFreq)) {
                    return $letter.$fragment;
                }

                return $matches[0];
            },
            $text,
        ) ?? $text;
    }

    /**
     * Classify a content heading: figure/table captions and prose or table
     * rows promoted to headings by the segmenter keep their text but leave
     * the lesson outline.
     *
     * @return 'heading'|'callout'|'paragraph'
     */
    private function headingDisposition(string $text): string
    {
        if (preg_match('/^(?:Figure|Table|Listing|Exhibit)\s+\d+-\d+/iu', $text) === 1) {
            return 'callout';
        }

        if (preg_match('/\s{2,}/', $text) === 1 || mb_strlen($text) > 80) {
            return 'paragraph';
        }

        if (preg_match('/^[^A-Za-z0-9]/u', $text) === 1 || preg_match('/^[a-z]/', $text) === 1) {
            return 'paragraph';
        }

        if (preg_match('/[=;{}$]|::/u', $text) === 1 || preg_match('/^\d{1,4}$/', $text) === 1) {
            return 'paragraph';
        }

        $wordCount = count(preg_split('/\s+/u', $text) ?: []);

        if ($wordCount >= 11 || (mb_strlen($text) >= 70 && $wordCount >= 9)) {
            return 'paragraph';
        }

        if (preg_match('/\.\s+[A-Z]/u', $text) === 1 || substr_count($text, ',') >= 4) {
            return 'paragraph';
        }

        if (str_ends_with($text, '.') || str_ends_with($text, ',') || str_ends_with($text, ';') || str_ends_with($text, ':')) {
            return 'paragraph';
        }

        if (mb_strtoupper($text) === $text && preg_match('/\p{L}{3}/u', $text) === 1 && $wordCount <= 4) {
            return 'paragraph';
        }

        return 'heading';
    }

    /**
     * Only output that could really come from running the listing is kept:
     * book captions ("Figure 10-2. …"), fatal error transcripts (stderr)
     * and section titles are not program output, so they neither render as
     * an output block nor grade an assert_output check.
     */
    private function trustedOutput(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $trimmed = trim($text);

        if (preg_match('/^(?:Figure|Table|Listing|Exhibit)\s+\d+-\d+/iu', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/^(?:PHP\s+)?(?:Fatal error|Parse error|Uncaught\s+\w+):/i', $trimmed) === 1) {
            return null;
        }

        if (preg_match('/^(?:[A-Z][a-z]+(?:\s+[A-Za-z]+){2,})$/u', $trimmed) === 1) {
            return null;
        }

        return $text;
    }

    /**
     * The listing's expected output: the next trusted output segment,
     * allowing up to two short "Output:"-style label lines (paragraphs,
     * demoted headings/callouts) in between. Anything longer — real
     * prose, another listing — means the output belongs elsewhere.
     *
     * @param  list<array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}>  $segments
     */
    private function expectedOutputFollowing(array $segments, int $index): ?string
    {
        $skipped = 0;

        for ($j = $index + 1, $n = count($segments); $j < $n && $skipped < 2; $j++) {
            $seg = $segments[$j];

            if ($seg['type'] === 'output') {
                return $this->trustedOutput($seg['text']);
            }

            $shortLabel = in_array($seg['type'], ['paragraph', 'heading', 'callout'], true)
                && trim($seg['text']) !== ''
                && mb_strlen(trim($seg['text'])) <= 80;

            if (! $shortLabel) {
                return null;
            }

            $skipped++;
        }

        return null;
    }

    /**
     * @param  array{type: string, text: string, items: list<string>, lang: string, listing_ref: string|null, page_pdf_from: int, page_pdf_to: int, page_printed_from: int, page_printed_to: int}  $seg
     * @return array{page_printed_from: int, page_printed_to: int, page_pdf_from: int, page_pdf_to: int}
     */
    private function stamp(array $seg): array
    {
        return [
            'page_printed_from' => $seg['page_printed_from'],
            'page_printed_to' => $seg['page_printed_to'],
            'page_pdf_from' => $seg['page_pdf_from'],
            'page_pdf_to' => $seg['page_pdf_to'],
        ];
    }

    private function deleteExisting(Lesson $lesson): void
    {
        $exerciseIds = Exercise::query()->where('lesson_id', $lesson->id)->pluck('id');

        if ($exerciseIds->isNotEmpty()) {
            ExerciseHint::query()->whereIn('exercise_id', $exerciseIds)->delete();
            ExerciseTest::query()->whereIn('exercise_id', $exerciseIds)->delete();
            Exercise::query()->whereIn('id', $exerciseIds)->forceDelete();
        }

        $questionIds = QuizQuestion::query()->where('lesson_id', $lesson->id)->pluck('id');

        if ($questionIds->isNotEmpty()) {
            QuizOption::query()->whereIn('question_id', $questionIds)->delete();
            QuizQuestion::query()->whereIn('id', $questionIds)->forceDelete();
        }

        Flashcard::query()->where('lesson_id', $lesson->id)->forceDelete();
        CodeExample::query()->where('lesson_id', $lesson->id)->delete();
        $lesson->blocks()->delete();
        $lesson->concepts()->detach();
    }

    /**
     * Concepts whose name or slug appears in the lesson text; refreshed as
     * the lesson's core links. Full names/slugs are matched first, then
     * distinctive name tokens so tool chapters ("Git, CI & Build") still
     * link when only a word of the name occurs. Candidates are all
     * published concepts because lesson_concepts starts empty (the graph is
     * seeded by this builder, not by an earlier pipeline run).
     *
     * @param  array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}  $stats
     * @return list<Concept>
     */
    private function linkConcepts(Lesson $lesson, string $text, array &$stats): array
    {
        $candidates = Concept::query()
            ->where('status', 'published')
            ->orderBy('name')
            ->get();

        $haystack = $this->wordPad($text);
        $matched = [];
        $seen = [];

        foreach ($candidates as $concept) {
            foreach ($this->strongNeedles($concept) as $needle) {
                if ($this->needleHit($haystack, $needle)) {
                    $matched[] = $concept;
                    $seen[$concept->id] = true;

                    break;
                }
            }

            if (count($matched) >= 8) {
                break;
            }
        }

        if (count($matched) < 8) {
            foreach ($candidates as $concept) {
                if (isset($seen[$concept->id])) {
                    continue;
                }

                foreach ($this->weakNeedles($concept) as $needle) {
                    if ($this->needleHit($haystack, $needle)) {
                        $matched[] = $concept;
                        $seen[$concept->id] = true;

                        break;
                    }
                }

                if (count($matched) >= 8) {
                    break;
                }
            }
        }

        if ($matched === []) {
            return [];
        }

        $lesson->concepts()->sync(array_map(static fn (Concept $c): int => $c->id, $matched));
        $stats['concepts'] = count($matched);

        return $matched;
    }

    /**
     * Lowercased text padded with spaces and reduced to word tokens, so a
     * needle matches on word boundaries regardless of punctuation.
     */
    private function wordPad(string $s): string
    {
        $flat = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)) ?? '';
        $flat = preg_replace('/\s+/u', ' ', $flat) ?? '';

        return ' '.trim($flat).' ';
    }

    /**
     * The full concept name and slug (strong evidence).
     *
     * @return list<string>
     */
    private function strongNeedles(Concept $concept): array
    {
        return array_values(array_unique([
            $this->wordPad((string) $concept->name),
            $this->wordPad(str_replace('_', ' ', (string) $concept->slug)),
        ]));
    }

    /**
     * Distinctive single words of the name and slug (weak evidence; only
     * consulted once no strong match is left).
     *
     * @return list<string>
     */
    private function weakNeedles(Concept $concept): array
    {
        $stop = [
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'when', 'while', 'what',
            'how', 'why', 'you', 'your', 'into', 'about', 'under', 'after', 'before',
            'each', 'every', 'some', 'other', 'more', 'most', 'than', 'then', 'them',
            'they', 'their', 'there', 'these', 'those', 'been', 'being', 'were', 'will',
            'would', 'could', 'should', 'also', 'just', 'only', 'very', 'much', 'many',
            'like', 'such', 'both', 'same', 'first', 'second', 'part', 'case', 'way',
            'use', 'used', 'using', 'make', 'made', 'best', 'good', 'new', 'old', 'all',
            'any', 'one', 'two', 'code', 'codes', 'thing', 'things', 'try', 'get', 'set',
            'run', 'see', 'can', 'may', 'not', 'but', 'are', 'was', 'has', 'had', 'own',
        ];

        $words = [];

        foreach (preg_split('/\s+/', $this->wordPad((string) $concept->name)) ?: [] as $word) {
            if ($word !== '' && mb_strlen($word) >= 3 && ! in_array($word, $stop, true)) {
                $words[] = $word;
            }
        }

        foreach (preg_split('/\s+/', $this->wordPad((string) $concept->slug)) ?: [] as $word) {
            if ($word !== '' && ! in_array($word, $stop, true)) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    /**
     * Whole-word hit, allowing a simple trailing plural.
     */
    private function needleHit(string $haystack, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '' || $needle === ' ') {
            return false;
        }

        if (str_contains($haystack, ' '.$needle.' ')) {
            return true;
        }

        if (! str_contains($needle, ' ')
            && (str_contains($haystack, ' '.$needle.'s ') || str_contains($haystack, ' '.$needle.'es '))) {
            return true;
        }

        return false;
    }

    /**
     * The lesson concept practice attempts should feed: the linked concept
     * whose name appears in the lesson title, else the first linked concept.
     *
     * @param  list<Concept>  $concepts
     */
    private function primaryConcept(Lesson $lesson, array $concepts): ?Concept
    {
        if ($concepts === []) {
            return null;
        }

        $titleTokens = preg_split('/\W+/u', mb_strtolower($lesson->title)) ?: [];

        foreach ($concepts as $concept) {
            $nameTokens = preg_split('/\W+/u', mb_strtolower((string) $concept->name)) ?: [];

            if (array_intersect($titleTokens, $nameTokens) !== []) {
                return $concept;
            }
        }

        return $concepts[0];
    }

    /**
     * Deterministic exercises: one write-the-listing exercise per book
     * listing (graded by static checks against the listing's distinctive
     * lines) and one explain exercise keyed on linked concept terms. Both
     * feed concept mastery through the lesson's primary concept.
     *
     * @param  list<Concept>  $concepts
     * @param  list<array{ref: string, code: string, intro: string|null, stamp: array<string, int>}>  $listings
     * @param  array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}  $stats
     * @return array{ids: list<int>, labels: list<string>}
     */
    private function createExercises(Lesson $lesson, array $concepts, array $listings, ?string $lastParagraph, array &$stats): array
    {
        $ids = [];
        $labels = [];
        $ord = 0;
        $primary = $this->primaryConcept($lesson, $concepts);

        foreach ($listings as $listing) {
            $intro = $listing['intro'] !== null ? rtrim($this->lastSentence($listing['intro']), " \t:.") : '';
            $prompt = 'Write PHP code for book listing '.$listing['ref'].'.'.($intro !== '' ? ' '.$intro.'.' : '');

            $exercise = Exercise::query()->create([
                'lesson_id' => $lesson->id,
                'concept_id' => $primary?->id,
                'type' => ExerciseType::Write,
                'difficulty' => 'easy',
                'prompt' => $prompt,
                'starter_code' => null,
                'solution_code' => $listing['code'],
                'explanation' => 'Compare your answer with listing '.$listing['ref'].' from the book.',
                'expected_answer' => null,
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
                'source' => ProvenanceSource::Book,
                'page_printed_from' => $listing['stamp']['page_printed_from'],
                'page_printed_to' => $listing['stamp']['page_printed_to'],
                'page_pdf_from' => $listing['stamp']['page_pdf_from'],
                'page_pdf_to' => $listing['stamp']['page_pdf_to'],
            ]);

            $needles = [];
            foreach (preg_split('/\r\n|\r|\n/', $listing['code']) ?: [] as $line) {
                $t = trim($line);

                if (mb_strlen($t) < 8 || str_starts_with($t, '//') || str_starts_with($t, '/*')) {
                    continue;
                }

                $keyworded = preg_match(
                    '/\b(?:class|interface|trait|function|namespace|new|public|private|protected|static|abstract|return|echo|print|var_dump|print_r|foreach|while|try|catch|throw|extends|implements|use|const|fn|match)\b/u',
                    $t,
                ) === 1;
                $objectish = mb_strlen($t) >= 12 && (str_contains($t, '::') || str_contains($t, '->'));

                if ($keyworded || $objectish) {
                    $needles[] = $t;
                }

                if (count($needles) === 2) {
                    break;
                }
            }

            if ($needles === []) {
                foreach (preg_split('/\r\n|\r|\n/', $listing['code']) ?: [] as $line) {
                    $t = trim($line);

                    if (mb_strlen($t) >= 9 && ! str_starts_with($t, '<?') && ! str_starts_with($t, '//') && ! str_starts_with($t, '/*') && ! str_starts_with($t, '*')) {
                        $needles[] = $t;
                        break;
                    }
                }
            }

            if ($needles !== []) {
                ExerciseTest::query()->create([
                    'exercise_id' => $exercise->id,
                    'ord' => 1,
                    'type' => 'static_check',
                    'payload' => ['required' => array_map(static fn (string $n): string => preg_quote($n, '/'), $needles)],
                    'weight' => 1,
                ]);
            }

            $ids[] = $exercise->id;
            $labels[] = mb_substr($prompt, 0, 70);
            $stats['exercises']++;
        }

        $keywords = array_slice(array_map(
            static fn (Concept $c): string => (string) $c->name,
            $concepts,
        ), 0, 6);

        if ($keywords !== []) {
            $exercise = Exercise::query()->create([
                'lesson_id' => $lesson->id,
                'concept_id' => $primary?->id,
                'type' => ExerciseType::Explain,
                'difficulty' => 'medium',
                'prompt' => 'Explain "'.$lesson->title.'" in your own words.',
                'starter_code' => null,
                'solution_code' => null,
                'explanation' => $lastParagraph ?? (string) $lesson->summary,
                'expected_answer' => ['keywords' => $keywords],
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
                'source' => ProvenanceSource::Book,
                'page_printed_from' => $lesson->page_printed_from,
                'page_printed_to' => $lesson->page_printed_to,
                'page_pdf_from' => $lesson->page_pdf_from,
                'page_pdf_to' => $lesson->page_pdf_to,
            ]);

            $ids[] = $exercise->id;
            $labels[] = mb_substr($exercise->prompt, 0, 70);
            $stats['exercises']++;
        }

        return ['ids' => $ids, 'labels' => $labels];
    }

    /**
     * @param  list<Concept>  $concepts
     * @param  array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}  $stats
     * @return array{ids: list<int>, labels: list<string>}
     */
    private function createQuiz(Lesson $lesson, array $concepts, array &$stats): array
    {
        $ids = [];
        $labels = [];
        $ord = 0;

        if ($concepts === []) {
            return ['ids' => [], 'labels' => []];
        }

        $pool = Concept::query()
            ->where('status', 'published')
            ->orderBy('name')
            ->get()
            ->all();

        $withDefinition = array_values(array_filter(
            $concepts,
            static fn (Concept $c): bool => trim((string) $c->definition) !== '',
        ));

        $correct = $withDefinition[0] ?? null;

        if ($correct !== null) {
            $distractors = [];

            foreach ($pool as $other) {
                if ($other->id === $correct->id) {
                    continue;
                }

                $definition = $this->firstSentence((string) $other->definition);

                if ($definition === '' || $definition === $this->firstSentence((string) $correct->definition)) {
                    continue;
                }

                $distractors[] = $definition;

                if (count($distractors) === 3) {
                    break;
                }
            }

            if ($distractors !== []) {
                $stem = 'Which statement best describes '.(string) $correct->name.'?';
                $ids[] = $this->insertQuiz($lesson, 'mcq', $stem, [$this->firstSentence((string) $correct->definition), ...$distractors], (string) $correct->definition, 0, $correct, $ord, $labels, $stats)['id'];
                $ord++;
            }
        }

        foreach ($withDefinition as $concept) {
            $sentence = null;

            foreach (preg_split('/(?<=[.!?])\s+/', (string) $concept->definition) ?: [] as $candidate) {
                if (stripos($candidate, (string) $concept->name) !== false) {
                    $sentence = trim($candidate);

                    break;
                }
            }

            if ($sentence === null) {
                continue;
            }

            $names = [];
            foreach ($pool as $other) {
                if ($other->id !== $concept->id) {
                    $names[] = (string) $other->name;
                }

                if (count($names) === 3) {
                    break;
                }
            }

            if ($names === []) {
                break;
            }

            $stem = 'Complete the sentence: '.preg_replace(
                '/'.preg_quote((string) $concept->name, '/').'/iu',
                '____',
                $sentence,
                1,
            );

            $result = $this->insertQuiz($lesson, 'completion', $stem, [(string) $concept->name, ...$names], (string) $concept->definition, 0, $concept, $ord, $labels, $stats);
            $ids[] = $result['id'];
            $ord++;

            break;
        }

        $tfConcept = $withDefinition[1] ?? null;

        if ($tfConcept !== null) {
            $sentence = $this->firstSentence((string) $tfConcept->definition);

            if ($sentence !== '') {
                $result = $this->insertQuiz(
                    $lesson,
                    'true_false',
                    'True or false: '.$sentence,
                    ['True', 'False'],
                    (string) $tfConcept->definition,
                    0,
                    $tfConcept,
                    $ord,
                    $labels,
                    $stats,
                );
                $ids[] = $result['id'];
                $ord++;
            }
        }

        return ['ids' => $ids, 'labels' => $labels];
    }

    /**
     * Insert one quiz question with rotated options so the correct answer
     * sits at a deterministic but lesson-dependent position.
     *
     * @param  list<string>  $options
     * @param  list<string>  $labels
     * @param  array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}  $stats
     * @return array{id: int}
     */
    private function insertQuiz(
        Lesson $lesson,
        string $type,
        string $stem,
        array $options,
        string $explanation,
        int $correctIndex,
        Concept $concept,
        int $ord,
        array &$labels,
        array &$stats,
    ): array {
        $options = array_values(array_unique(array_filter(array_map(static fn (string $o): string => trim(mb_substr($o, 0, 200)), $options), static fn (string $o): bool => $o !== '')));

        $correctText = $options[$correctIndex] ?? ($options[0] ?? '');
        $distractors = array_values(array_filter($options, static fn (string $o): bool => $o !== $correctText));
        $slot = $lesson->id % max(1, count($distractors) + 1);
        $ordered = [...array_slice($distractors, 0, $slot), $correctText, ...array_slice($distractors, $slot)];

        $question = QuizQuestion::query()->create([
            'lesson_id' => $lesson->id,
            'concept_id' => $concept->id,
            'type' => $type,
            'stem' => $stem,
            'explanation' => $explanation,
            'difficulty' => 'easy',
            'ord' => $ord + 1,
            'status' => ContentStatus::InReview,
            'source' => ProvenanceSource::Book,
            'page_printed_from' => $lesson->page_printed_from,
            'page_printed_to' => $lesson->page_printed_to,
            'page_pdf_from' => $lesson->page_pdf_from,
            'page_pdf_to' => $lesson->page_pdf_to,
        ]);

        foreach ($ordered as $index => $text) {
            QuizOption::query()->create([
                'question_id' => $question->id,
                'text' => $text,
                'is_correct' => $text === $correctText,
                'feedback' => null,
                'ord' => $index + 1,
            ]);
        }

        $labels[] = mb_substr($stem, 0, 70);
        $stats['quiz']++;

        return ['id' => $question->id];
    }

    /**
     * Definition cards for linked concepts plus one section-recap card.
     *
     * @param  list<Concept>  $concepts
     * @param  list<array{heading: string, stamp: array<string, int>, next: array<string, mixed>|null}>  $headingPairs
     * @param  array{blocks: int, code_examples: int, exercises: int, quiz: int, cards: int, concepts: int}  $stats
     * @return list<string>
     */
    private function createCards(Lesson $lesson, array $concepts, array $headingPairs, array &$stats): array
    {
        $labels = [];
        $ord = 0;

        $make = function (string $front, string $back, ?Concept $concept) use ($lesson, &$ord, &$labels, &$stats): void {
            Flashcard::query()->create([
                'concept_id' => $concept?->id,
                'lesson_id' => $lesson->id,
                'card_type' => 'definition',
                'front' => $front,
                'back' => $back,
                'ord' => ++$ord,
                'status' => ContentStatus::InReview,
                'source' => ProvenanceSource::Book,
                'page_printed_from' => $lesson->page_printed_from,
                'page_printed_to' => $lesson->page_printed_to,
                'page_pdf_from' => $lesson->page_pdf_from,
                'page_pdf_to' => $lesson->page_pdf_to,
            ]);

            $labels[] = mb_substr($front, 0, 70);
            $stats['cards']++;
        };

        foreach (array_slice($concepts, 0, 4) as $concept) {
            $definition = trim((string) $concept->definition);

            if ($definition !== '') {
                $make((string) $concept->name, $definition, $concept);
            }
        }

        foreach ($headingPairs as $pair) {
            if (count($labels) >= 5) {
                break;
            }

            $next = $pair['next'] ?? null;

            if ($next === null || $next['type'] !== 'paragraph' || $pair['heading'] === '📌 Summary' || $pair['heading'] === '🧠 Key concepts') {
                continue;
            }

            if (preg_match('/^Chapter\s+\d+\s+-\s+/iu', $pair['heading']) === 1) {
                continue;
            }

            $sentence = $this->firstSentence($next['text']);

            if ($sentence !== '') {
                $make('What does the “'.$pair['heading'].'” section cover?', $sentence, null);
            }

            break;
        }

        return $labels;
    }

    private function deriveTitle(?string $intro, string $sectionTitle): string
    {
        if ($intro === null || trim($intro) === '') {
            return $sectionTitle;
        }

        $title = rtrim($this->lastSentence($intro), " \t:.");

        if ($title === '' || mb_strlen($title) > 90) {
            return $sectionTitle;
        }

        return $title;
    }

    private function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return '';
        }

        if (preg_match('/^([^.!?]+[.!?])/u', $text, $m) === 1) {
            return trim($m[1]);
        }

        return mb_substr($text, 0, 200);
    }

    private function lastSentence(string $text): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];

        return trim((string) end($parts));
    }

    private function norm(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        $s = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], "'", $s);
        $s = str_replace(["\u{2013}", "\u{2014}"], '-', $s);

        return mb_strtolower($s);
    }

    /**
     * Match-tolerant form of a heading line: norm() minus whitespace and
     * common heading punctuation, so drop-cap headings ("E xceptions") and
     * colon titles ("Appendix A: Bibliography") still equal their section
     * titles.
     */
    private function looseKey(string $s): string
    {
        return str_replace([' ', "\t", ':', '?', '.', ',', ';', '"', "'", '-'], '', $this->norm($s));
    }

    /**
     * looseKey() of the title plus its first-char rotation, because some
     * drop-cap headings extract with the leading letter at line end
     * ("SR-4 AutoloadingP").
     *
     * @return list<string>
     */
    private function looseVariants(string $title): array
    {
        $loose = $this->looseKey($title);
        $variants = [$loose];

        if (mb_strlen($loose) > 3) {
            $variants[] = mb_substr($loose, 1).mb_substr($loose, 0, 1);
        }

        return $variants;
    }

    /**
     * Standalone marker line that opens a chapter in the extracted text:
     * "CHAPTER 12" for numbered chapters, "APPENDIX A" for appendices.
     */
    private function markerPattern(Chapter $chapter): string
    {
        if (preg_match('/^Appendix\s+([A-Z])/i', (string) $chapter->title, $m) === 1) {
            return '/^APPENDIX\s+'.$m[1].'$/iu';
        }

        if (preg_match('/^Front\s+matter/i', (string) $chapter->title) === 1) {
            return '/^About the Author$/iu';
        }

        return '/^CHAPTER\s+'.(int) $chapter->number.'$/iu';
    }

    /**
     * First PDF page whose header line is the index; content must never
     * extend into it (the book's tail is not lesson material).
     */
    private function indexStartPage(): ?int
    {
        $rows = DB::table('pdf_pages')->orderBy('page_pdf')->get(['page_pdf', 'text']);

        foreach ($rows as $row) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $row->text) ?: [];

            foreach ($lines as $line) {
                $t = trim($line);

                if ($t === '') {
                    continue;
                }

                if (preg_match('/^INDEX$/i', $t) === 1) {
                    return (int) $row->page_pdf;
                }

                break;
            }
        }

        return null;
    }

    /**
     * @return array{pdf: int, line: int, top: bool}|null
     */
    private function findChapterMarker(Chapter $chapter, int $fromPdf, int $fromLine): ?array
    {
        $pattern = $this->markerPattern($chapter);
        $max = (int) DB::table('pdf_pages')->max('page_pdf');

        for ($pdf = $fromPdf; $pdf <= min($max, $fromPdf + 60); $pdf++) {
            $lines = $this->linesOf($pdf);
            $start = $pdf === $fromPdf ? $fromLine : 0;

            foreach ($lines as $li => $line) {
                if ($li < $start) {
                    continue;
                }

                if (preg_match($pattern, trim($line)) === 1) {
                    return ['pdf' => $pdf, 'line' => $li, 'top' => $this->isTopLine($lines, $li)];
                }
            }
        }

        return null;
    }

    /**
     * @return array{pdf: int, line: int, consumed: int, top: bool}|null
     */
    private function findTitle(string $title, int $fromPdf, int $fromLine): ?array
    {
        $normTitle = $this->norm($title);
        $looseTitles = $this->looseVariants($title);

        if ($normTitle === '') {
            return null;
        }

        $max = (int) DB::table('pdf_pages')->max('page_pdf');
        $hitMarker = false;

        for ($pdf = $fromPdf; $pdf <= min($max, $fromPdf + 60); $pdf++) {
            $lines = $this->linesOf($pdf);

            foreach ($lines as $li => $line) {
                if ($pdf === $fromPdf && $li < $fromLine) {
                    continue;
                }

                if (trim($line) === '') {
                    continue;
                }

                // The cursor may sit on the current chapter's own marker
                // (the opener case); only later markers stop the scan.
                if (! ($pdf === $fromPdf && $li === $fromLine)
                    && preg_match('/^(?:CHAPTER\s+\d+|APPENDIX\s+[A-Z])$/iu', trim($line)) === 1) {
                    $hitMarker = true;

                    break;
                }

                for ($w = 0; $w <= 2; $w++) {
                    if ($li + $w >= count($lines)) {
                        break;
                    }

                    $joined = $this->norm(implode(' ', array_slice($lines, $li, $w + 1)));

                    if ($joined === $normTitle
                        || ($w === 0 && mb_strlen($title) > 4 && $joined === mb_strtolower($this->norm(substr($title, 1))))
                        || in_array($this->looseKey($joined), $looseTitles, true)) {
                        return ['pdf' => $pdf, 'line' => $li, 'consumed' => $w + 1, 'top' => $this->isTopLine($lines, $li)];
                    }
                }
            }

            if ($hitMarker) {
                break;
            }
        }

        return null;
    }

    /**
     * @param  list<array{pdf: int, printed: int, text: string}>  $pages
     * @return array{p: int, line: int, consumed: int}|null
     */
    private function findTitleIn(array $pages, string $title, int $fromP, int $fromLine): ?array
    {
        $normTitle = $this->norm($title);
        $looseTitles = $this->looseVariants($title);

        if ($normTitle === '') {
            return null;
        }

        foreach ($pages as $pi => $page) {
            $lines = preg_split('/\r\n|\r|\n/', $page['text']) ?: [];

            foreach ($lines as $li => $line) {
                if ($pi === $fromP && $li < $fromLine) {
                    continue;
                }

                if (trim($line) === '') {
                    continue;
                }

                for ($w = 0; $w <= 2; $w++) {
                    if ($li + $w >= count($lines)) {
                        break;
                    }

                    $joined = $this->norm(implode(' ', array_slice($lines, $li, $w + 1)));

                    if ($joined === $normTitle
                        || ($w === 0 && mb_strlen($title) > 4 && $joined === mb_strtolower($this->norm(substr($title, 1))))
                        || in_array($this->looseKey($joined), $looseTitles, true)) {
                        return ['p' => $pi, 'line' => $li, 'consumed' => $w + 1];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function linesOf(int $pdf): array
    {
        if (! isset($this->pageLines[$pdf])) {
            $row = DB::table('pdf_pages')->where('page_pdf', $pdf)->first(['page_pdf', 'page_printed', 'text']);

            if ($row === null) {
                return [];
            }

            $this->printed[$pdf] = (int) $row->page_printed;
            $clean = $this->segmenter->clean([
                ['pdf' => (int) $row->page_pdf, 'printed' => (int) $row->page_printed, 'text' => (string) $row->text],
            ]);

            $this->pageLines[$pdf] = preg_split('/\r\n|\r|\n/', $clean[0]['text']) ?: [];
        }

        return $this->pageLines[$pdf];
    }

    /**
     * @param  list<string>  $lines
     */
    private function isTopLine(array $lines, int $li): bool
    {
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            return $index === $li;
        }

        return false;
    }

    private function writeRange(Section $section, int $fromPdf, int $toPdf): bool
    {
        if ($fromPdf < 1 || $toPdf < $fromPdf) {
            return false;
        }

        if (! isset($this->printed[$fromPdf])) {
            $this->printed[$fromPdf] = (int) (DB::table('pdf_pages')->where('page_pdf', $fromPdf)->value('page_printed') ?? 0);
        }

        if (! isset($this->printed[$toPdf])) {
            $this->printed[$toPdf] = (int) (DB::table('pdf_pages')->where('page_pdf', $toPdf)->value('page_printed') ?? 0);
        }

        $fromPrinted = $this->printed[$fromPdf];
        $toPrinted = $this->printed[$toPdf];

        $changed = (int) $section->page_pdf_from !== $fromPdf
            || (int) $section->page_pdf_to !== $toPdf
            || (int) $section->page_printed_from !== $fromPrinted
            || (int) $section->page_printed_to !== $toPrinted;

        if ($changed) {
            Section::query()->whereKey($section->id)->update([
                'page_pdf_from' => $fromPdf,
                'page_pdf_to' => $toPdf,
                'page_printed_from' => $fromPrinted,
                'page_printed_to' => $toPrinted,
            ]);

            $section->page_pdf_from = $fromPdf;
            $section->page_pdf_to = $toPdf;
            $section->page_printed_from = $fromPrinted;
            $section->page_printed_to = $toPrinted;
        }

        return $changed;
    }
}
