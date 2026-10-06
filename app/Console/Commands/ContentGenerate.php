<?php

namespace App\Console\Commands;

use App\Jobs\DerivePrerequisitesJob;
use App\Jobs\ExtractConceptsJob;
use App\Jobs\GenerateLessonJob;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Stage;
use Illuminate\Console\Command;

/**
 * Dispatch Pipeline A for every book-backed chapter of a stage: concept
 * extraction first, one lesson job per leaf section, prerequisite
 * derivation last (docs/10 job chain; docs/15 Phase 7).
 */
class ContentGenerate extends Command
{
    protected $signature = 'content:generate
        {stage : Stage number or slug (1-11; Stage 0 is seeded, not generated)}
        {--dry-run : Print the plan without dispatching anything}
        {--sync : Run the jobs inline instead of queueing them}';

    protected $description = 'Generate in-review content for a stage through the AI pipeline';

    public function handle(): int
    {
        $ref = (string) $this->argument('stage');

        $stage = Stage::query()
            ->where('number', ctype_digit($ref) ? (int) $ref : -1)
            ->orWhere('slug', $ref)
            ->first();

        if ($stage === null) {
            $this->error("Stage '{$ref}' not found.");

            return self::FAILURE;
        }

        if ($stage->number === 0) {
            $this->error('Stage 0 has no book source - run php artisan db:seed --class=Stage0FoundationSeeder.');

            return self::FAILURE;
        }

        $chapterNumbers = $this->chapterNumbers($stage->subtitle);

        if ($chapterNumbers === []) {
            $this->error("Could not read book chapters from stage subtitle \"{$stage->subtitle}\".");

            return self::FAILURE;
        }

        $chapters = Chapter::query()->whereIn('number', $chapterNumbers)->orderBy('number')->get();

        if ($chapters->isEmpty()) {
            $this->error('No imported chapters match this stage. Run book:import + book:detect first.');

            return self::FAILURE;
        }

        $plan = [];
        $queued = 0;
        $skipped = 0;

        foreach ($chapters as $chapter) {
            $sections = Section::query()
                ->where('chapter_id', $chapter->id)
                ->whereDoesntHave('children')
                ->whereNotNull('page_printed_from')
                ->orderBy('ord')
                ->get();

            $fresh = $sections->reject(static function (Section $section) use (&$skipped): bool {
                $published = Lesson::query()
                    ->where('section_id', $section->id)
                    ->where('status', 'published')
                    ->exists();

                if ($published) {
                    $skipped++;
                }

                return $published;
            });

            $plan[] = ['Ch. '.$chapter->number, count($sections).' sections, '.count($fresh).' to generate'];
            $queued += count($fresh);

            if ($this->option('dry-run')) {
                continue;
            }

            ExtractConceptsJob::dispatch($chapter->id);

            foreach ($fresh as $section) {
                $this->dispatchJob(new GenerateLessonJob($section->id, $stage->id));
            }
        }

        if (! $this->option('dry-run')) {
            $this->dispatchJob(new DerivePrerequisitesJob);
        }

        $this->table(['Chapter', 'Plan'], $plan);
        $this->line(($this->option('dry-run') ? '[dry-run] ' : '')."{$queued} lessons to generate, {$skipped} published sections skipped.");

        if (! $this->option('dry-run')) {
            $this->line($this->option('sync')
                ? 'Jobs ran inline - review them at /admin/review.'
                : 'Jobs queued - watch them at /admin/import-jobs (php artisan queue:work to run).');
        }

        return self::SUCCESS;
    }

    private function dispatchJob(object $job): void
    {
        if ($this->option('sync')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }
    }

    /**
     * Parse "Book chapters 1–2" / "Book chapters 3-4" / "Book chapter 22 + Appendix B" -> [1, 2].
     *
     * Stage subtitles use en-dashes (StageSeeder), so ranges must accept
     * -, – and — between the numbers.
     *
     * @return list<int>
     */
    private function chapterNumbers(string $subtitle): array
    {
        if (preg_match('/chapters?\s+(\d+)(?:\s*[-–—]\s*(\d+))?/u', $subtitle, $match) !== 1) {
            return [];
        }

        $from = (int) $match[1];
        $to = isset($match[2]) ? (int) $match[2] : $from;

        return range($from, $to);
    }
}
