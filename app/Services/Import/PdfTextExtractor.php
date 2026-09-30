<?php

namespace App\Services\Import;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    /**
     * Extract every page of a PDF as text (layout preserved).
     *
     * @return array{page_count: int, pages: list<string>}
     */
    public function extract(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("PDF not found: {$absolutePath}");
        }

        $pageCount = $this->pageCount($absolutePath);

        $process = new Process([
            $this->binary('pdftotext'),
            '-layout',
            '-enc',
            'UTF-8',
            $absolutePath,
            '-',
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdftotext failed: '.$process->getErrorOutput());
        }

        $pages = explode("\f", $process->getOutput());

        // pdftotext emits a trailing form feed, producing one empty tail element.
        if (end($pages) === '') {
            array_pop($pages);
        }

        return [
            'page_count' => $pageCount > 0 ? $pageCount : count($pages),
            'pages' => $pages,
        ];
    }

    public function pageCount(string $absolutePath): int
    {
        $process = new Process([
            $this->binary('pdfinfo'),
            $absolutePath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Resolve an extraction binary: config override, known install path, PATH.
     */
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
}
