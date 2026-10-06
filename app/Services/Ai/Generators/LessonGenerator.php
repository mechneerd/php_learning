<?php

namespace App\Services\Ai\Generators;

use App\Models\Chapter;
use App\Models\Section;
use App\Services\Ai\Drafts\LessonDraft;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;
use App\Services\Content\BookExcerpt;

/**
 * GenerateLessonJob's prompt builder: section excerpt + concepts ->
 * a full-template LessonDraft (status lands as in_review).
 */
final class LessonGenerator extends Generator
{
    public function __construct(
        GenerationRunner $runner,
        PromptRunner $helpers,
        private readonly BookExcerpt $excerpt,
    ) {
        parent::__construct($runner, $helpers);
    }

    /**
     * @param  list<string>  $conceptSlugs
     * @param  list<string>  $prereqSlugs
     */
    public function generate(Section $section, Chapter $chapter, array $conceptSlugs, array $prereqSlugs): ?LessonDraft
    {
        $pages = 'pp. '.$section->page_printed_from.'-'.($section->page_printed_to ?? $section->page_printed_from);
        $sourceLine = "Chapter {$chapter->number} \"{$chapter->title}\", {$pages}, section \"{$section->title}\".";

        $hash = $this->hash('lesson', [
            (string) $section->id,
            $section->title,
            (string) $section->page_pdf_from,
            (string) $section->page_pdf_to,
            implode(',', $conceptSlugs),
        ]);

        if ($this->runner->done('lesson', $hash)) {
            return null;
        }

        $excerpt = $this->excerpt->forSection($section);

        $system = $this->system(
            $sourceLine,
            'Explanation level: build up from first principles; never assume the reader already knows OOP. '
            .'Cover what/why/prereqs/simple/analogy/visual/syntax/examples/mistakes/usage/summary; '
            .'leave exercise/quiz/card reference blocks with empty labels.',
            LessonDraft::schema(),
        );

        $prompt = "Book excerpt:\n\"\"\"\n{$excerpt}\n\"\"\"\n\n"
            .'Concepts to cover: '.implode(', ', $conceptSlugs)."\n"
            .'Prerequisites already taught: '.($prereqSlugs === [] ? '(none)' : implode(', ', $prereqSlugs));

        $json = $this->runner->run(
            'lesson',
            'lesson',
            null,
            $hash,
            $system,
            $prompt,
            LessonDraft::parses(...),
            ['section_slug' => $section->slug],
        );

        return LessonDraft::fromArray((array) json_decode($json, true));
    }
}
