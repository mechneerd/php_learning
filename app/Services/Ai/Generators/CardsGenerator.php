<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\CardsDraft;

/**
 * GenerateCardsJob's prompt builder: lesson context -> flashcards.
 */
final class CardsGenerator extends Generator
{
    /**
     * @param  list<string>  $conceptSlugs
     */
    public function generate(Lesson $lesson, array $conceptSlugs): ?CardsDraft
    {
        $citation = $lesson->citation() ?? 'this lesson';

        $hash = $this->hash('cards', [(string) $lesson->id, $lesson->slug, implode(',', $conceptSlugs)]);

        if ($this->runner->done('cards', $hash)) {
            return null;
        }

        $system = $this->system(
            "Lesson \"{$lesson->title}\", {$citation}.",
            'Write 3-8 cards: definitions, syntax shapes, and "what is the difference" pairs. '
            .'Front is the prompt, back is a one- or two-sentence answer.',
            CardsDraft::schema(),
        );

        $prompt = 'Lesson summary: '.((string) $lesson->summary)."\n"
            .'Concepts: '.($conceptSlugs === [] ? '(none listed)' : implode(', ', $conceptSlugs));

        $json = $this->runner->run(
            'cards',
            'flashcard',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            CardsDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return CardsDraft::fromArray((array) json_decode($json, true));
    }
}
