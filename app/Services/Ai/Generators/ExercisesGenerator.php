<?php

namespace App\Services\Ai\Generators;

use App\Models\Lesson;
use App\Services\Ai\Drafts\ExerciseDraft;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;
use App\Services\Content\BookExcerpt;

/**
 * GenerateExercisesJob's prompt builder: lesson context -> practice items
 * with hint ladders and static-grade answers.
 */
final class ExercisesGenerator extends Generator
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
     */
    public function generate(Lesson $lesson, array $conceptSlugs): ?ExerciseDraft
    {
        $citation = $lesson->citation() ?? 'this lesson';

        $hash = $this->hash('exercise', [(string) $lesson->id, $lesson->slug, implode(',', $conceptSlugs)]);

        if ($this->runner->done('exercise', $hash)) {
            return null;
        }

        $excerpt = $this->excerpt->forRange($lesson->page_pdf_from, $lesson->page_pdf_to, 3000);

        $system = $this->system(
            "Lesson \"{$lesson->title}\", {$citation}.",
            'Write 2-4 short practice items a learner can complete in this lesson alone. '
            .'Each needs 1-3 escalating hints (concept first, syntax second, implementation last) '
            .'and an answer suitable for static grading (exact string or short list of accepted strings).',
            ExerciseDraft::schema(),
        );

        $prompt = 'Lesson summary: '.((string) $lesson->summary)."\n"
            ."Book excerpt:\n\"\"\"\n{$excerpt}\n\"\"\"\n\n"
            .'Concepts: '.($conceptSlugs === [] ? '(none listed)' : implode(', ', $conceptSlugs));

        $json = $this->runner->run(
            'exercise',
            'exercise',
            $lesson->id,
            $hash,
            $system,
            $prompt,
            ExerciseDraft::parses(...),
            ['lesson_slug' => $lesson->slug],
        );

        return ExerciseDraft::fromArray((array) json_decode($json, true));
    }
}
