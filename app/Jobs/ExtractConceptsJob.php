<?php

namespace App\Jobs;

use App\Enums\ContentSource;
use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Concept;
use App\Services\Ai\Generators\ConceptsGenerator;
use App\Services\Content\BookExcerpt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: chapter sections -> concept entries for the graph
 * (status in_review; existing slugs are never overwritten).
 */
class ExtractConceptsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $chapterId) {}

    public function handle(ConceptsGenerator $generator, BookExcerpt $excerpt): void
    {
        $chapter = Chapter::query()->with('sections')->findOrFail($this->chapterId);

        $sections = $chapter->sections;

        if ($sections->isEmpty()) {
            return;
        }

        $titles = [];
        foreach ($sections as $section) {
            $titles[] = $section->title;
        }

        $range = [
            $sections->min('page_pdf_from'),
            $sections->max('page_pdf_to'),
        ];

        $draft = $generator->generate($chapter, $titles, $excerpt->forRange($range[0], $range[1], 4000));

        if ($draft === null) {
            return;
        }

        foreach ($draft->concepts as $concept) {
            Concept::query()->firstOrCreate(
                ['slug' => $concept['slug']],
                [
                    'name' => $concept['name'],
                    'definition' => $concept['definition'],
                    'skill_domain' => $concept['skill_domain'],
                    'granularity' => $concept['granularity'],
                    'is_core' => false,
                    'source' => ContentSource::Ai,
                    'status' => ContentStatus::InReview,
                ],
            );
        }
    }
}
