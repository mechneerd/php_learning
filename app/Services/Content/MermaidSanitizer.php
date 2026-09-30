<?php

namespace App\Services\Content;

/**
 * Makes stored Mermaid source safe to hand to the browser renderer.
 *
 * Strips interaction handlers and any javascript: URI, and enforces a
 * length ceiling so a hostile/huge diagram cannot lock the tab.
 */
final class MermaidSanitizer
{
    public const MAX_LENGTH = 20000;

    public function sanitize(string $source): string
    {
        $source = str_replace("\r\n", "\n", $source);
        $source = preg_replace('/javascript:/i', '', $source) ?? '';
        $source = preg_replace('/^\s*click\s+.*$/m', '', $source) ?? '';
        $source = preg_replace('/\n{3,}/', "\n\n", $source) ?? '';

        if (strlen($source) > self::MAX_LENGTH) {
            $source = substr($source, 0, self::MAX_LENGTH);
        }

        return trim($source);
    }

    public function isRenderable(string $source): bool
    {
        return $this->sanitize($source) !== '';
    }
}
