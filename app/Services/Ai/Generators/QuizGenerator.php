<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\QuizDraft;

/**
 * GenerateQuizJob's prompt builder: lesson context -> quiz questions.
 */
final class QuizGenerator extends Generator
{
    /**
     * @param  list<string>  $conceptSlugs
     */
    public function generate(Lesson $lesson, array $conceptSlugs): ?QuizDraft
    {
        $citation = $lesson->citation() ?? 'this lesson';

        $hash = $this->hash('quiz', [(string) $lesson->id, $lesson->slug, implode(',', $conceptSlugs)]);

        if ($this->runner->done('quiz', $hash)) {
            return null;
        }

        $system = $this->system(
            "Lesson \"{$lesson->title}\", {$citation}.",
            'Write 3-5 questions testing understanding, not memory. For option-based types give 2-4 '
            .'options with exactly one correct (correct = its zero-based index) and a one-sentence '
            .'explanation of why it is right.',
            QuizDraft::schema(),
        );

        $prompt = 'Lesson summary: '.((string) $lesson->summary)."\n"
            .'Concepts: '.($conceptSlugs === [] ? '(none listed)' : implode(', ', $conceptSlugs));

        $json = $this->runner->run(
            'quiz',
            'quiz',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            QuizDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return QuizDraft::fromArray((array) json_decode($json, true));
    }
}
