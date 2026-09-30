<?php

namespace App\Concerns;

use App\Enums\ProvenanceSource;

/**
 * Provenance fields shared by every piece of book/AI content.
 *
 * Columns: source, book_id, chapter_id, section_id, page_printed_from/to,
 * page_pdf_from/to, status, ai_model, ai_generated_at, ai_prompt_version,
 * reviewed_by, reviewed_at, is_outdated.
 */
trait HasProvenance
{
    /**
     * @return array<string, string>
     */
    protected function provenanceCasts(): array
    {
        return [
            'source' => ProvenanceSource::class,
            'ai_generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_outdated' => 'boolean',
        ];
    }

    public function isFromBook(): bool
    {
        return $this->source === ProvenanceSource::Book;
    }

    public function isAiGenerated(): bool
    {
        return $this->source === ProvenanceSource::Ai;
    }

    public function sourceLabel(): string
    {
        return $this->source->label();
    }

    /**
     * Human-readable citation, e.g. "Ch. 3 · pp. 29–33 · PDF pages 49–53".
     */
    public function citation(): ?string
    {
        $parts = [];

        if ($this->chapter_id !== null) {
            $chapter = $this->relationLoaded('chapter') || method_exists($this, 'chapter')
                ? $this->chapter
                : null;

            if ($chapter !== null) {
                $parts[] = 'Ch. '.$chapter->number;
            }
        }

        if ($this->page_printed_from !== null) {
            $parts[] = $this->pageRange('page_printed_from', 'page_printed_to', 'pp. ');
        }

        if ($this->page_pdf_from !== null) {
            $parts[] = $this->pageRange('page_pdf_from', 'page_pdf_to', 'PDF ');
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    protected function pageRange(string $from, string $to, string $prefix): string
    {
        $start = $this->{$from};
        $end = $this->{$to};

        return $prefix.($end !== null && $end !== $start ? "{$start}–{$end}" : (string) $start);
    }
}
