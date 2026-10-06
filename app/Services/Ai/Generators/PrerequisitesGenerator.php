<?php

namespace App\Services\Ai\Generators;

use App\Services\Ai\Drafts\PrerequisitesDraft;

/**
 * DerivePrerequisitesJob's prompt builder: the concept set in teaching
 * order -> prerequisite edges (cycle-checked when persisted).
 */
final class PrerequisitesGenerator extends Generator
{
    /**
     * @param  list<string>  $slugs  concepts in curriculum order
     */
    public function generate(array $slugs): ?PrerequisitesDraft
    {
        if ($slugs === []) {
            return null;
        }

        $hash = $this->hash('prerequisites', [implode(',', $slugs)]);

        if ($this->runner->done('prerequisites', $hash)) {
            return null;
        }

        $system = $this->system(
            'Whole curriculum concept set.',
            'Link each concept only to what must genuinely come first. Never link a concept to itself '
            .'and never create a cycle (A needs B needs A). Prefer few confident edges over many guesses.',
            PrerequisitesDraft::schema(),
        );

        $prompt = 'Concepts in curriculum order: '.implode(', ', $slugs);

        $json = $this->runner->run(
            'prerequisites',
            'concept',
            null,
            $hash,
            $system,
            $prompt,
            PrerequisitesDraft::parses(...),
            ['count' => count($slugs)],
        );

        return PrerequisitesDraft::fromArray((array) json_decode($json, true));
    }
}
