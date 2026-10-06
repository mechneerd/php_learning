<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\CodeExampleDraft;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;
use App\Services\Content\BookExcerpt;

/**
 * GenerateCodeExamplesJob's prompt builder: book listings in the lesson's
 * page range -> tiered code examples.
 */
final class CodeExamplesGenerator extends Generator
{
    public function __construct(
        GenerationRunner $runner,
        PromptRunner $helpers,
        private readonly BookExcerpt $excerpt,
    ) {
        parent::__construct($runner, $helpers);
    }

    public function generate(Lesson $lesson): ?CodeExampleDraft
    {
        $citation = $lesson->citation() ?? 'this lesson';

        $hash = $this->hash('code_examples', [(string) $lesson->id, $lesson->slug, (string) $lesson->page_pdf_from]);

        if ($this->runner->done('code_examples', $hash)) {
            return null;
        }

        $excerpt = $this->excerpt->forRange($lesson->page_pdf_from, $lesson->page_pdf_to, 4000);

        $system = $this->system(
            "Lesson \"{$lesson->title}\", {$citation}.",
            'Extract or lightly adapt 1-3 runnable examples from the excerpt. Tier 1 = smallest piece, '
            .'tier 2 = the core pattern of the lesson, tier 3+ = realistic use. Code must be complete '
            .'enough to run standalone; expected_output is the exact CLI output or null if it does not print.',
            CodeExampleDraft::schema(),
        );

        $prompt = 'Lesson summary: '.((string) $lesson->summary)."\n\n"
            ."Book excerpt:\n\"\"\"\n{$excerpt}\n\"\"\"";

        $json = $this->runner->run(
            'code_examples',
            'code_example',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            CodeExampleDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return CodeExampleDraft::fromArray((array) json_decode($json, true));
    }
}
