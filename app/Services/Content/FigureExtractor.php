<?php

namespace App\Services\Content;

use App\Enums\ProvenanceSource;
use App\Models\LessonMedia;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolves book figure references ("Figure 16-1") to real images.
 *
 * Screenshot-style figures are extracted as embedded rasters with
 * pdfimages. Vector diagrams (UML, flowcharts — their labels are often
 * drawn as curves, not text) are rendered from the caption's page with
 * pdftoppm: pdftotext -bbox locates the caption line, the figure region
 * is the whitespace (and any label text) immediately above or below it,
 * and pdftoppm crops the page to that region at 200 DPI.
 *
 * Every method fails soft (returns null) so lesson builds never break
 * when the binary, the PDF, or a figure is missing.
 */
final class FigureExtractor
{
    private const MIN_WIDTH = 200;

    private const MIN_HEIGHT = 150;

    /** Render scale for vector figure crops (book body ≈ 10 pt at 72 dpi). */
    private const RENDER_DPI = 200;

    /** Vertical whitespace (points) tighter than this still belongs to the figure. */
    private const LINE_GAP = 14.0;

    /** A crop taller than this fraction of the page is clamped. */
    private const MAX_CROP_FRACTION = 0.5;

    /** @var array<int, array{width: int, height: int}>|null pdf page => qualifying image dims, null until booted */
    private ?array $imagePages = null;

    /** @var array<string, array{page_pdf: int, caption: string|null, alt: string, src: string, width: int, height: int, media_id: int|null}> */
    private array $byRef = [];

    /**
     * Resolve a figure reference for a lesson: ensure the image file is
     * extracted (raster) or rendered (vector), upsert the lesson_media
     * row (first lesson keeps it), and return the block payload fields.
     *
     * @return array{page_pdf: int, caption: string|null, alt: string, src: string, media_id: int|null, width: int, height: int}|null
     */
    public function find(string $figureRef, int $lessonId): ?array
    {
        if (preg_match('/^\d+-\d+$/', $figureRef) !== 1) {
            return null;
        }

        if (isset($this->byRef[$figureRef])) {
            return $this->byRef[$figureRef];
        }

        $page = $this->figurePage($figureRef);

        if ($page !== null) {
            $file = $this->ensureFile($page['page_pdf']);
        } else {
            $captionPage = $this->captionPage($figureRef);

            if ($captionPage === null) {
                return null;
            }

            $file = $this->renderVector($figureRef, $captionPage['page_pdf']);
            $page = $captionPage;
        }

        if ($file === null) {
            return null;
        }

        $page['width'] = $file['width'];
        $page['height'] = $file['height'];

        $caption = $this->captionFor($figureRef, $page['page_pdf']);
        $alt = $this->altFor($figureRef, $caption);
        $src = 'figures/'.$file['file'];

        $mediaId = null;

        if ($lessonId > 0) {
            $mediaId = $this->persistMedia($figureRef, $lessonId, $caption, $alt, $src, $file, $page);
        }

        $result = [
            'page_pdf' => $page['page_pdf'],
            'caption' => $caption,
            'alt' => $alt,
            'src' => $src,
            'media_id' => $mediaId,
            'width' => $file['width'],
            'height' => $file['height'],
        ];

        return $this->byRef[$figureRef] = $result;
    }

    /**
     * Find the PDF page whose caption line carries both the figure
     * reference and a qualifying raster image. Captions start a line and
     * follow the ref with "." or ":" ("Figure 16-1. Adding a package…");
     * prose mentions ("…see Figure 16-2.") are ignored on purpose.
     *
     * @return array{page_pdf: int, page_printed: int|null, width: int, height: int}|null
     */
    private function figurePage(string $figureRef): ?array
    {
        $this->boot();

        if ($this->imagePages === []) {
            return null;
        }

        $rows = DB::table('pdf_pages')
            ->where('text', 'like', '%Figure '.$figureRef.'%')
            ->orderBy('page_pdf')
            ->get(['page_pdf', 'page_printed', 'text']);

        foreach ($rows as $row) {
            $pdf = (int) $row->page_pdf;

            if (! isset($this->imagePages[$pdf])) {
                continue;
            }

            if ($this->captionLine($figureRef, (string) $row->text) === null) {
                continue;
            }

            return [
                'page_pdf' => $pdf,
                'page_printed' => $row->page_printed !== null ? (int) $row->page_printed : null,
                'width' => $this->imagePages[$pdf]['width'],
                'height' => $this->imagePages[$pdf]['height'],
            ];
        }

        return null;
    }

    /**
     * The page that carries the figure's caption line, regardless of
     * whether an extractable raster image lives there.
     *
     * @return array{page_pdf: int, page_printed: int|null}|null
     */
    private function captionPage(string $figureRef): ?array
    {
        $rows = DB::table('pdf_pages')
            ->where('text', 'like', '%Figure '.$figureRef.'%')
            ->orderBy('page_pdf')
            ->get(['page_pdf', 'page_printed', 'text']);

        foreach ($rows as $row) {
            if ($this->captionLine($figureRef, (string) $row->text) === null) {
                continue;
            }

            return [
                'page_pdf' => (int) $row->page_pdf,
                'page_printed' => $row->page_printed !== null ? (int) $row->page_printed : null,
            ];
        }

        return null;
    }

    /**
     * Render a vector figure: locate the caption line via pdftotext
     * -bbox, grow a region upward/downward over the adjacent whitespace
     * (diagram labels are frequently drawn as curves, so the region is
     * mostly blank in the text layer), and crop the page with pdftoppm
     * at RENDER_DPI. Cached as public/figures/figure-{ref}.png.
     *
     * @return array{file: string, width: int, height: int}|null relative to public/
     */
    private function renderVector(string $figureRef, int $pagePdf): ?array
    {
        $dir = public_path('figures');
        $file = 'figure-'.$figureRef.'.png';
        $target = $dir.DIRECTORY_SEPARATOR.$file;

        if (is_file($target)) {
            $dims = @getimagesize($target);

            if ($dims !== false) {
                return ['file' => $file, 'width' => (int) $dims[0], 'height' => (int) $dims[1]];
            }
        }

        try {
            $pdf = $this->bookPdfPath();

            if ($pdf === null) {
                return null;
            }

            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return null;
            }

            $region = $this->figureRegion($figureRef, $pagePdf, $pdf);

            $tmp = $dir.DIRECTORY_SEPARATOR.'._render-'.$figureRef.'-';

            if ($region === null) {
                // Whole-page fallback so the figure is at least visible.
                [$code] = $this->run([$this->binary('pdftoppm'), '-f', (string) $pagePdf, '-l', (string) $pagePdf, '-r', (string) self::RENDER_DPI, '-png', '-singlefile', $pdf, $tmp]);
            } else {
                $scale = self::RENDER_DPI / 72;
                $x = (int) floor($region['x'] * $scale);
                $y = (int) floor($region['y'] * $scale);
                $w = (int) ceil($region['w'] * $scale);
                $h = (int) ceil($region['h'] * $scale);
                [$code] = $this->run([$this->binary('pdftoppm'), '-f', (string) $pagePdf, '-l', (string) $pagePdf, '-r', (string) self::RENDER_DPI, '-png', '-x', (string) $x, '-y', (string) $y, '-W', (string) $w, '-H', (string) $h, '-singlefile', $pdf, $tmp]);
            }

            if ($code !== 0 || ! is_file($tmp.'.png')) {
                return null;
            }

            if (! @rename($tmp.'.png', $target)) {
                return null;
            }

            $dims = @getimagesize($target);

            if ($dims === false || (int) $dims[0] < 40 || (int) $dims[1] < 20) {
                @unlink($target);

                return null;
            }

            return ['file' => $file, 'width' => (int) $dims[0], 'height' => (int) $dims[1]];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Crop rectangle (PDF points) for the figure above/below its caption
     * line, derived from the page's word boxes. Returns null when the
     * bbox layer cannot be read (caller falls back to a full page).
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function figureRegion(string $figureRef, int $pagePdf, string $pdf): ?array
    {
        $lines = $this->bboxLines($pagePdf, $pdf);

        if ($lines === []) {
            return null;
        }

        $captionIdx = null;

        foreach ($lines as $idx => $line) {
            if (preg_match('/^Figure\s+'.preg_quote($figureRef, '/').'\s*[.:]/u', $line['text']) === 1) {
                $captionIdx = $idx;

                break;
            }
        }

        if ($captionIdx === null) {
            return null;
        }

        $caption = $lines[$captionIdx];
        $pageHeight = 0.0;
        $bodyLeft = $caption['xmin'];
        $bodyRight = $caption['xmax'];

        foreach ($lines as $line) {
            $pageHeight = max($pageHeight, $line['ymax']);
            $bodyLeft = min($bodyLeft, $line['xmin']);
            $bodyRight = max($bodyRight, $line['xmax']);
        }

        $pageHeight += 36.0;

        // Grow over adjacent label text (gap tighter than LINE_GAP, but
        // never over prose/code — a figure may abut the listing above)…
        $aboveTop = null;

        for ($i = $captionIdx - 1; $i >= 0; $i--) {
            if ($this->looksLikeBodyText($lines[$i]['text'])) {
                $aboveTop = $lines[$i]['ymax'] + 2.0;

                break;
            }

            $gap = $caption['ymin'] - $lines[$i]['ymax'];

            if ($gap > self::LINE_GAP) {
                break;
            }

            $aboveTop = $aboveTop === null ? $lines[$i]['ymin'] : min($aboveTop, $lines[$i]['ymin']);
            $caption = array_merge($caption, ['ymin' => $lines[$i]['ymin']]);
        }

        // …or, when the diagram's labels are curves (no text at all),
        // the figure is the blank gap itself between neighbouring text.
        if ($aboveTop === null) {
            $aboveTop = $caption['ymin'];

            for ($i = $captionIdx - 1; $i >= 0; $i--) {
                if ($this->looksLikeBodyText($lines[$i]['text'])) {
                    $aboveTop = $lines[$i]['ymax'] + 2.0;

                    break;
                }

                $gap = $caption['ymin'] - $lines[$i]['ymax'];

                if ($gap > self::LINE_GAP) {
                    $aboveTop = $lines[$i]['ymax'] + 4.0;

                    break;
                }
            }

            if ($aboveTop >= $caption['ymin']) {
                // Nothing but the running head above: the figure is the
                // blank body area from the top margin down to the caption.
                $aboveTop = 72.0;
            }
        }

        $belowBottom = null;
        $captionBottom = $caption['ymax'];

        for ($i = $captionIdx + 1, $n = count($lines); $i < $n; $i++) {
            if ($this->looksLikeBodyText($lines[$i]['text'])) {
                break;
            }

            $gap = $lines[$i]['ymin'] - $captionBottom;

            if ($gap > self::LINE_GAP) {
                break;
            }

            $belowBottom = max($belowBottom ?? 0.0, $lines[$i]['ymax']);
            $captionBottom = $belowBottom;
        }

        if ($belowBottom === null) {
            $belowBottom = $captionBottom;

            for ($i = $captionIdx + 1, $n = count($lines); $i < $n; $i++) {
                if ($this->looksLikeBodyText($lines[$i]['text'])) {
                    $belowBottom = $lines[$i]['ymin'] - 2.0;

                    break;
                }

                $gap = $lines[$i]['ymin'] - $captionBottom;

                if ($gap > self::LINE_GAP) {
                    $belowBottom = $lines[$i]['ymin'] - 4.0;

                    break;
                }
            }
        }

        $capTopOriginal = $lines[$captionIdx]['ymin'];
        $capBottomOriginal = $lines[$captionIdx]['ymax'];
        $aboveH = $capTopOriginal - $aboveTop;
        $belowH = $belowBottom - $capBottomOriginal;
        $maxH = self::MAX_CROP_FRACTION * $pageHeight;

        if ($belowH > $aboveH + 8.0) {
            $y = $capBottomOriginal + 2.0;
            $h = min($belowH - 4.0, $maxH);
        } else {
            $y = max($aboveTop, $capTopOriginal - $maxH);
            $h = min($capTopOriginal - $y - 2.0, $maxH);
        }

        if ($h < 20.0 || $bodyRight - $bodyLeft < 40.0) {
            return null;
        }

        return ['x' => max(0.0, $bodyLeft - 4.0), 'y' => max(0.0, $y), 'w' => $bodyRight - $bodyLeft + 8.0, 'h' => $h];
    }

    /**
     * Prose wraps and PHP listings must never be absorbed into a figure
     * crop: diagram labels are short and free of code punctuation.
     */
    private function looksLikeBodyText(string $text): bool
    {
        if (mb_strlen($text) > 70) {
            return true;
        }

        return preg_match('/[=$;{}<>]|::|\/\//', $text) === 1;
    }

    /**
     * Word-box lines of one page (top to bottom) from pdftotext -bbox.
     * Running heads and folios are dropped so they cannot anchor a crop.
     *
     * @return list<array{xmin: float, ymin: float, xmax: float, ymax: float, text: string}>
     */
    private function bboxLines(int $pagePdf, string $pdf): array
    {
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'bbox');

            if ($tmp === false) {
                return [];
            }

            $xmlFile = $tmp.'.xml';
            @unlink($tmp);
            [$code, $out] = $this->run([$this->binary('pdftotext'), '-f', (string) $pagePdf, '-l', (string) $pagePdf, '-bbox', $pdf, $xmlFile]);

            if ($code !== 0 || ! is_file($xmlFile)) {
                return [];
            }

            $xml = file_get_contents($xmlFile);
            @unlink($xmlFile);

            if (! is_string($xml) || $xml === '') {
                return [];
            }

            // Embedded control bytes make otherwise valid XML unparseable.
            $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml) ?? '';

            $doc = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $loaded = $doc->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (! $loaded) {
                return [];
            }

            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query('//*[local-name()="word"]');

            if ($nodes === false) {
                return [];
            }

            /** @var array<int, array{xmin: float, ymin: float, xmax: float, ymax: float, words: list<string>}> $grouped */
            $grouped = [];

            foreach ($nodes as $word) {
                if (! $word instanceof DOMElement) {
                    continue;
                }

                $xmin = (float) $word->getAttribute('xMin');
                $xmax = (float) $word->getAttribute('xMax');
                $ymin = (float) $word->getAttribute('yMin');
                $ymax = (float) $word->getAttribute('yMax');
                $key = (int) round((($ymin + $ymax) / 2) / 3);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = ['xmin' => $xmin, 'ymin' => $ymin, 'xmax' => $xmax, 'ymax' => $ymax, 'words' => []];
                }

                $grouped[$key]['xmin'] = min($grouped[$key]['xmin'], $xmin);
                $grouped[$key]['xmax'] = max($grouped[$key]['xmax'], $xmax);
                $grouped[$key]['ymin'] = min($grouped[$key]['ymin'], $ymin);
                $grouped[$key]['ymax'] = max($grouped[$key]['ymax'], $ymax);
                $grouped[$key]['words'][] = $word->textContent;
            }

            ksort($grouped);
            $lines = [];

            foreach ($grouped as $group) {
                $text = trim(preg_replace('/\s+/', ' ', implode(' ', $group['words'])) ?? '');

                if ($text === '' || preg_match('/^\d{1,4}$/', $text) === 1) {
                    continue;
                }

                if ($group['ymax'] < 70.0) {
                    continue;
                }

                $lines[] = ['xmin' => $group['xmin'], 'ymin' => $group['ymin'], 'xmax' => $group['xmax'], 'ymax' => $group['ymax'], 'text' => $text];
            }

            return $lines;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Build the page => image map once per process (pdfimages -list).
     * Skipped pages: front matter/cover (page <= 2) and anything below
     * the size threshold (rules out rules, bullets, small decorations).
     */
    private function boot(): void
    {
        if ($this->imagePages !== null) {
            return;
        }

        $this->imagePages = [];

        try {
            $pdf = $this->bookPdfPath();

            if ($pdf === null) {
                return;
            }

            $binary = $this->binary('pdfimages');
            [$code, $out] = $this->run([$binary, '-list', $pdf]);

            if ($code !== 0) {
                return;
            }

            foreach (preg_split('/\r\n|\r|\n/', $out) ?: [] as $line) {
                if (preg_match('/^\s*(\d+)\s+\d+\s+\S+\s+(\d+)\s+(\d+)\s/', $line, $m) !== 1) {
                    continue;
                }

                $page = (int) $m[1];
                $w = (int) $m[2];
                $h = (int) $m[3];

                if ($page <= 2 || $w < self::MIN_WIDTH || $h < self::MIN_HEIGHT) {
                    continue;
                }

                $area = $w * $h;

                if (! isset($this->imagePages[$page]) || $area > $this->imagePages[$page]['width'] * $this->imagePages[$page]['height']) {
                    $this->imagePages[$page] = ['width' => $w, 'height' => $h];
                }
            }
        } catch (Throwable) {
            $this->imagePages = [];
        }
    }

    /**
     * Extract the page's largest qualifying image to
     * public/figures/figure-{page}.png (cached on disk).
     *
     * @return array{file: string, width: int, height: int}|null relative to public/
     */
    private function ensureFile(int $pagePdf): ?array
    {
        $dir = public_path('figures');
        $file = 'figure-'.$pagePdf.'.png';
        $target = $dir.DIRECTORY_SEPARATOR.$file;

        if (is_file($target)) {
            $dims = @getimagesize($target);

            if ($dims !== false) {
                return ['file' => $file, 'width' => (int) $dims[0], 'height' => (int) $dims[1]];
            }
        }

        try {
            $pdf = $this->bookPdfPath();

            if ($pdf === null) {
                return null;
            }

            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return null;
            }

            $tmp = $dir.DIRECTORY_SEPARATOR.'._extract-'.$pagePdf.'-';
            $binary = $this->binary('pdfimages');
            [$code] = $this->run([$binary, '-png', '-f', (string) $pagePdf, '-l', (string) $pagePdf, $pdf, $tmp]);

            if ($code !== 0) {
                return null;
            }

            $best = null;
            $bestArea = 0;

            foreach ((glob($tmp.'*.png') ?: []) as $candidate) {
                $dims = @getimagesize($candidate);

                if ($dims === false) {
                    continue;
                }

                $w = (int) $dims[0];
                $h = (int) $dims[1];
                $area = $w * $h;

                if ($w >= self::MIN_WIDTH && $h >= self::MIN_HEIGHT && $area > $bestArea) {
                    $best = $candidate;
                    $bestArea = $area;
                }
            }

            foreach ((glob($tmp.'*.png') ?: []) as $leftover) {
                if ($leftover !== $best) {
                    @unlink($leftover);
                }
            }

            if ($best === null) {
                return null;
            }

            if (! @rename($best, $target)) {
                return null;
            }

            $dims = getimagesize($target);

            if ($dims === false) {
                @unlink($target);

                return null;
            }

            return ['file' => $file, 'width' => (int) $dims[0], 'height' => (int) $dims[1]];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{file: string, width: int, height: int}  $file
     * @param  array{page_pdf: int, page_printed: int|null, width: int, height: int}  $page
     */
    private function persistMedia(string $figureRef, int $lessonId, ?string $caption, string $alt, string $src, array $file, array $page): ?int
    {
        try {
            $media = LessonMedia::query()->firstOrCreate(
                ['figure_ref' => $figureRef],
                [
                    'lesson_id' => $lessonId,
                    'caption' => $caption,
                    'alt' => $alt,
                    'path' => $src,
                    'disk' => 'public',
                    'width' => $file['width'],
                    'height' => $file['height'],
                    'source' => ProvenanceSource::Book,
                    'page_printed' => $page['page_printed'] ?? null,
                    'page_pdf' => $page['page_pdf'],
                ],
            );

            return (int) $media->id;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The figure's caption line: starts the line, follows the ref with
     * "." or ":", i.e. the book's caption — not a prose mention.
     */
    private function captionFor(string $figureRef, int $pagePdf): ?string
    {
        try {
            $text = DB::table('pdf_pages')->where('page_pdf', $pagePdf)->value('text');

            if (! is_string($text) || $text === '') {
                return null;
            }

            $caption = $this->captionLine($figureRef, $text);

            if ($caption === null) {
                return null;
            }

            $caption = trim(preg_replace('/\s+/', ' ', $caption) ?? '');

            return $caption === '' ? null : $caption;
        } catch (Throwable) {
            return null;
        }
    }

    private function captionLine(string $figureRef, string $text): ?string
    {
        $pattern = '/^Figure\s+'.preg_quote($figureRef, '/').'\s*[.:]\s*\S[^\n]*/mu';

        if (preg_match($pattern, $text, $m) !== 1) {
            return null;
        }

        $line = trim($m[0]);

        return $line === '' ? null : $line;
    }

    private function altFor(string $figureRef, ?string $caption): string
    {
        if ($caption !== null && preg_match('/^Figure\s+\d+-\d+\.?$/iu', $caption) !== 1) {
            return $caption;
        }

        return 'Figure '.$figureRef.' from PHP 8 Objects, Patterns, and Practice';
    }

    private function bookPdfPath(): ?string
    {
        try {
            $path = DB::table('pdf_documents')
                ->where('status', 'extracted')
                ->orderBy('id')
                ->value('path');

            if (! is_string($path) || $path === '') {
                return null;
            }

            foreach (['app/private/', 'app/'] as $prefix) {
                $candidate = storage_path($prefix.$path);

                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function binary(string $name): string
    {
        $configured = config("import.{$name}_binary");

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $osKey = PHP_OS_FAMILY === 'Windows' ? 'windows' : 'unix';

        foreach ((array) config("import.detected_paths.{$name}.{$osKey}", []) as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return $name;
    }

    /**
     * @param  list<string>  $cmd
     * @return array{0: int, 1: string}
     */
    private function run(array $cmd): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes, base_path());

        if (! is_resource($process)) {
            return [1, ''];
        }

        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $out.$err];
    }
}
