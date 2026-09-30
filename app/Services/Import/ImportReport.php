<?php

namespace App\Services\Import;

/**
 * Result of an import, ready for command output, admin UI, and tests.
 */
final class ImportReport
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $documentId = 0,
        public readonly int $pagesStored = 0,
        public readonly int $pagesFlagged = 0,
        public readonly int $chapters = 0,
        public readonly int $sections = 0,
        public readonly int $locatedByHeading = 0,
        public readonly int $locatedByMap = 0,
        public readonly int $unlocated = 0,
        public readonly array $warnings = [],
    ) {}

    /**
     * @param  array{document_id: int, pages: int, flagged: int}  $extract
     */
    public static function fromExtract(array $extract): self
    {
        return new self(
            documentId: $extract['document_id'],
            pagesStored: $extract['pages'],
            pagesFlagged: $extract['flagged'],
        );
    }

    /**
     * @param  array{
     *     chapters: int, sections: int, located_by_heading: int,
     *     located_by_map: int, unlocated: int, openers_found: int
     * }  $detect
     */
    public function withDetection(array $detect): self
    {
        return new self(
            documentId: $this->documentId,
            pagesStored: $this->pagesStored,
            pagesFlagged: $this->pagesFlagged,
            chapters: $detect['chapters'],
            sections: $detect['sections'],
            locatedByHeading: $detect['located_by_heading'],
            locatedByMap: $detect['located_by_map'],
            unlocated: $detect['unlocated'],
            warnings: $this->warnings,
        );
    }

    public function withWarning(string $warning): self
    {
        $warnings = $this->warnings;
        $warnings[] = $warning;

        return new self(
            documentId: $this->documentId,
            pagesStored: $this->pagesStored,
            pagesFlagged: $this->pagesFlagged,
            chapters: $this->chapters,
            sections: $this->sections,
            locatedByHeading: $this->locatedByHeading,
            locatedByMap: $this->locatedByMap,
            unlocated: $this->unlocated,
            warnings: $warnings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'pages_stored' => $this->pagesStored,
            'pages_flagged' => $this->pagesFlagged,
            'chapters' => $this->chapters,
            'sections' => $this->sections,
            'located_by_heading' => $this->locatedByHeading,
            'located_by_map' => $this->locatedByMap,
            'unlocated' => $this->unlocated,
            'warnings' => $this->warnings,
        ];
    }
}
