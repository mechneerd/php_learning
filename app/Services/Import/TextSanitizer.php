<?php

namespace App\Services\Import;

class TextSanitizer
{
    /**
     * Clean pdftotext output before storage.
     *
     * - strips control characters (BEL, BS) emitted for symbol fonts
     * - replaces dot-leader glyphs (U+FFFD) and non-breaking spaces
     * - collapses runs of blank lines
     */
    public static function clean(string $text): string
    {
        $text = str_replace(["\x07", "\x08", "\x00"], '', $text);
        $text = str_replace(["\u{FFFD}", "\u{00A0}"], ' ', $text);
        $text = str_replace("\r\n", "\n", $text);

        // Tabs at line start are layout artifacts from -layout mode.
        $text = preg_replace('/\t+/', ' ', $text) ?? $text;

        // Collapse 3+ newlines into exactly one blank line.
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
