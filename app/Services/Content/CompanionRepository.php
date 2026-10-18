<?php

namespace App\Services\Content;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * The official Apress companion repository for the book
 * (github.com/Apress/php-8-objects-patterns-practice), cloned under
 * storage/app/private/companion/.
 *
 * Mapping a listing reference ("13.16") to a repository file:
 *   1. contents.txt — the authoritative ref-to-path map shipped with the repo.
 *   2. The "listing NN.NN" comment markers inside the PHP source files —
 *      covers ~115 markers contents.txt omits.
 *
 * Everything fails soft (empty index / null root) so builds and commands
 * work without the repository present.
 */
final class CompanionRepository
{
    /** @var array<string, string>|null listing ref => relative file path, null until loaded */
    private ?array $contents = null;

    /** @var array<string, string>|null listing ref => relative file path (with marker anchor), null until indexed */
    private ?array $markers = null;

    public function root(): ?string
    {
        try {
            $configured = config('import.companion_path');

            if (is_string($configured) && $configured !== '') {
                return is_dir($configured) ? rtrim($configured, '/\\') : null;
            }

            $candidates = [
                storage_path('app/private/companion/php-8-objects-patterns-practice'),
                storage_path('app/companion/php-8-objects-patterns-practice'),
            ];

            foreach ($candidates as $candidate) {
                if (is_dir($candidate)) {
                    return rtrim($candidate, '/\\');
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The repository's own contents.txt map: "13.16" => "src/ch13/batch01/SpaceMapper.php".
     * Entries whose file does not actually exist on disk are dropped.
     *
     * @return array<string, string>
     */
    public function contentsMap(): array
    {
        if ($this->contents !== null) {
            return $this->contents;
        }

        $this->contents = [];
        $root = $this->root();

        if ($root === null) {
            return $this->contents;
        }

        try {
            $path = $root.DIRECTORY_SEPARATOR.'contents.txt';

            if (! is_file($path)) {
                return $this->contents;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if (! is_array($lines)) {
                return $this->contents;
            }

            $current = null;

            foreach ($lines as $line) {
                if (preg_match('/^(\d{2}\.\d{2}):\s*$/', $line, $match) === 1) {
                    $current = $match[1];

                    continue;
                }

                if ($current === null) {
                    continue;
                }

                if (preg_match('/^\s{2,}(\S.*?)\s*$/', $line, $match) === 1) {
                    $target = str_replace('\\', '/', $match[1]);

                    if (! isset($this->contents[$current]) && is_file($root.DIRECTORY_SEPARATOR.$target)) {
                        $this->contents[$current] = $target;
                    }

                    continue;
                }

                if (trim($line) !== '') {
                    $current = null;
                }
            }
        } catch (Throwable) {
            $this->contents = [];
        }

        return $this->contents;
    }

    /**
     * All listing references found in the repository's PHP files:
     * "13.16" => "src/ch13/batch01/SpaceMapper.php#listing-13.16".
     *
     * @return array<string, string>
     */
    public function markerIndex(): array
    {
        if ($this->markers !== null) {
            return $this->markers;
        }

        $this->markers = [];
        $root = $this->root();

        if ($root === null) {
            return $this->markers;
        }

        try {
            $srcDir = $root.DIRECTORY_SEPARATOR.'src';
            $base = is_dir($srcDir) ? $srcDir : $root;
            $normalizedRoot = str_replace('\\', '/', $root);

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = @file_get_contents($file->getPathname());

                if (! is_string($content) || $content === '') {
                    continue;
                }

                if (preg_match_all('/\/\*\s*listing\s+(\d+\.\d+)\s*\*\//', $content, $matches) === 0 || $matches[1] === []) {
                    continue;
                }

                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($normalizedRoot) + 1);

                foreach ($matches[1] as $ref) {
                    if (! isset($this->markers[$ref])) {
                        $this->markers[$ref] = $relative.'#listing-'.$ref;
                    }
                }
            }
        } catch (Throwable) {
            $this->markers = [];
        }

        return $this->markers;
    }

    /**
     * Best-known source file for a listing reference: contents.txt first
     * (authoritative), marker scan second (fills contents.txt gaps).
     */
    public function locate(string $listingRef): ?string
    {
        return $this->contentsMap()[$listingRef] ?? $this->markerIndex()[$listingRef] ?? null;
    }
}
