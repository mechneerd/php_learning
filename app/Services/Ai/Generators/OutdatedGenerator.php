<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\OutdatedDraft;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;
use App\Services\Content\BookExcerpt;

/**
 * DetectOutdatedJob's prompt builder: lesson blocks + book year ->
 * is_outdated flag plus BOOK/MODERN/WHY panel copy.
 */
final class OutdatedGenerator extends Generator
{
    public function __construct(
        GenerationRunner $runner,
        PromptRunner $helpers,
        private readonly BookExcerpt $excerpt,
    ) {
        parent::__construct($runner, $helpers);
    }

    public function generate(Lesson $lesson): ?OutdatedDraft
    {
        $hash = $this->hash('outdated', [(string) $lesson->id, (string) $lesson->updated_at?->getTimestamp()]);

        if ($this->runner->done('outdated', $hash)) {
            return null;
        }

        $excerpt = $this->excerpt->forRange($lesson->page_pdf_from, $lesson->page_pdf_to, 2500);

        $system = $this->system(
            'Book published 2021 (PHP 8 era).',
            'Decide whether this lesson teaches anything PHP has since replaced. book = one sentence '
            .'summarising what the book teaches; modern = the PHP 8.5-current equivalent; why = why the '
            .'change happened. Set is_outdated true only when a learner would write something legacy.',
            OutdatedDraft::schema(),
        );

        $prompt = 'Lesson: '.$lesson->title."\nSummary: ".((string) $lesson->summary)."\n\n"
            ."Book excerpt:\n\"\"\"\n{$excerpt}\n\"\"\"";

        $json = $this->runner->run(
            'outdated',
            'lesson',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            OutdatedDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return OutdatedDraft::fromArray((array) json_decode($json, true));
    }
}
