<?php

namespace App\Services\Ai\Generators;

use App\Models\Chapter;
use App\Services\Ai\Drafts\ConceptsDraft;

/**
 * ExtractConceptsJob's prompt builder: a chapter's section titles and
 * excerpt -> concept entries for the graph.
 */
final class ConceptsGenerator extends Generator
{
    /**
     * @param  list<string>  $sectionTitles
     */
    public function generate(Chapter $chapter, array $sectionTitles, string $excerpt): ?ConceptsDraft
    {
        $hash = $this->hash('concepts', [
            (string) $chapter->id,
            implode('|', $sectionTitles),
            (string) mb_strlen($excerpt),
        ]);

        if ($this->runner->done('concepts', $hash)) {
            return null;
        }

        $system = $this->system(
            "Chapter {$chapter->number} \"{$chapter->title}\".",
            'Extract the 5-15 core concepts a learner must take from these sections. '
            .'Slug is lowercase kebab-case; definition is one plain sentence; skill_domain is a short '
            .'cluster name (e.g. oop, patterns, practice).',
            ConceptsDraft::schema(),
        );

        $prompt = 'Sections: '.implode(' | ', $sectionTitles)."\n\n"
            ."Excerpt:\n\"\"\"\n{$excerpt}\n\"\"\"";

        $json = $this->runner->run(
            'concepts',
            'concept',
            null,
            $hash,
            $system,
            $prompt,
            ConceptsDraft::parses(...),
            ['chapter_number' => $chapter->number],
        );

        return ConceptsDraft::fromArray((array) json_decode($json, true));
    }
}
