<?php

namespace App\Services\Import;

class PrintedPageDetector
{
    /**
     * Lines produced by the publisher as page furniture; they may follow the
     * folio number at the bottom of a page (chapter openers, for example).
     */
    private const FOOTER_PATTERNS = [
        '/^©/',
        '/^M\. Zandstra,\s+PHP/',
        '/doi\.org\//i',
        '/^\d+\s+M\. Zandstra/',
        '/^https?:\/\//',
    ];

    /**
     * Detect the printed page number of an extracted page.
     *
     * @param  list<string>  $lines
     */
    public static function fromLines(array $lines): ?int
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^\d{1,4}$/', $line) === 1) {
                return (int) $line;
            }

            if (self::isFooter($line)) {
                continue;
            }

            break;
        }

        return null;
    }

    public static function fromText(string $text): ?int
    {
        return self::fromLines(explode("\n", $text));
    }

    private static function isFooter(string $line): bool
    {
        foreach (self::FOOTER_PATTERNS as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }
}
