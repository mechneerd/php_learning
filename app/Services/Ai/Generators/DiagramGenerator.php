<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\DiagramDraft;

/**
 * GenerateDiagramJob's prompt builder: lesson context -> one Mermaid
 * diagram (kind chosen by whatever shows the structure best).
 */
final class DiagramGenerator extends Generator
{
    /**
     * @param  list<string>  $conceptSlugs
     */
    public function generate(Lesson $lesson, array $conceptSlugs): ?DiagramDraft
    {
        $citation = $lesson->citation() ?? 'this lesson';

        $hash = $this->hash('diagram', [(string) $lesson->id, $lesson->slug, implode(',', $conceptSlugs)]);

        if ($this->runner->done('diagram', $hash)) {
            return null;
        }

        $system = $this->system(
            "Lesson \"{$lesson->title}\", {$citation}.",
            'Output exactly one Mermaid source that fits the idea (class diagram for structures, '
            .'flowchart for processes). Keep it under 20 nodes; label edges with short verbs.',
            DiagramDraft::schema(),
        );

        $prompt = 'Lesson summary: '.((string) $lesson->summary)."\n"
            .'Concepts: '.($conceptSlugs === [] ? '(none listed)' : implode(', ', $conceptSlugs));

        $json = $this->runner->run(
            'diagram',
            'diagram',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            DiagramDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return DiagramDraft::fromArray((array) json_decode($json, true));
    }
}
