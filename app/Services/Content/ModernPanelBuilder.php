<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Models\Lesson;
use App\Services\Ai\Drafts\OutdatedDraft;

/**
 * Builds the BOOK / MODERN / WHY modern-panel block payload (docs/04
 * modern panels; BlockType::ModernPanel). Used by DetectOutdatedJob with
 * an AI draft, and directly by seeders with lesson-derived fallback copy.
 */
final class ModernPanelBuilder
{
    /**
     * @return array{book: string, modern: string, why: string}
     */
    public function fromDraft(OutdatedDraft $draft): array
    {
        return [
            'book' => $draft->book,
            'modern' => $draft->modern,
            'why' => $draft->why,
        ];
    }

    /**
     * Non-AI fallback: derive the three panes from the lesson itself.
     *
     * @return array{book: string, modern: string, why: string}
     */
    public function forLesson(Lesson $lesson): array
    {
        $title = $lesson->title;
        $summary = (string) $lesson->summary;

        return [
            'book' => "The book teaches {$title} as: {$summary} "
                .'Keep this pane as the book-era baseline.',
            'modern' => "In current PHP, apply {$title} with constructor property promotion, "
                .'readonly where immutability helps, and typed properties throughout.',
            'why' => 'Modern PHP moved the same idea into less boilerplate: types are enforced '
                .'at the engine, and immutable data removes whole classes of bugs.',
        ];
    }
}
