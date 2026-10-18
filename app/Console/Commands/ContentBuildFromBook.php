<?php

namespace App\Console\Commands;

use App\Models\CodeExample;
use App\Models\Exercise;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Content\BookLessonBuilder;
use App\Services\Content\ContentApprover;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Deterministic rebuild of every lesson's content from the extracted
 * book pages (docs/15: content built from the PDF, no AI calls), then
 * the standard admin approval gate for each derived entity.
 */
class ContentBuildFromBook extends Command
{
    protected $signature = 'content:build
        {--lesson= : Build a single lesson id}
        {--dry-run : Roll back after building and only report}
        {--no-approve : Leave rebuilt rows in_review}';

    protected $description = 'Rebuild lesson blocks, listings, exercises, quiz and cards from the book pages';

    public function handle(BookLessonBuilder $builder, ContentApprover $approver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $admin = User::query()->where('role', 'admin')->orderBy('id')->first();

        $this->info('Repairing section page ranges...');

        $repair = $builder->repairRanges();
        $this->line(sprintf('Ranges: %d checked, %d fixed.', $repair['checked'], $repair['fixed']));

        foreach ($repair['warnings'] as $warning) {
            $this->warn($warning);
        }

        $query = Lesson::query()->orderBy('id');

        if (($lessonId = $this->option('lesson')) !== null) {
            $query->whereKey((int) $lessonId);
        }

        $lessons = $query->get();
        $totals = ['blocks' => 0, 'code_examples' => 0, 'exercises' => 0, 'quiz' => 0, 'cards' => 0, 'concepts' => 0];
        $approved = 0;
        $errors = 0;

        foreach ($lessons as $lesson) {
            try {
                $stats = $builder->build($lesson, $dryRun);

                $this->line(sprintf(
                    'lesson #%d %s: blocks=%d examples=%d exercises=%d quiz=%d cards=%d concepts=%d',
                    $lesson->id,
                    $lesson->title,
                    $stats['blocks'],
                    $stats['code_examples'],
                    $stats['exercises'],
                    $stats['quiz'],
                    $stats['cards'],
                    $stats['concepts'],
                ));

                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + $stats[$key];
                }

                if (! $dryRun && ! $this->option('no-approve') && $admin !== null) {
                    $approved += $this->approveLesson($approver, $lesson, $admin);
                }
            } catch (Throwable $e) {
                $errors++;
                $this->warn('lesson #'.$lesson->id.' failed: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%sTotals: %d lessons attempted, %d blocks, %d code examples, %d exercises, %d quiz questions, %d cards, %d concept links.',
            $dryRun ? '[dry-run] ' : '',
            $lessons->count(),
            $totals['blocks'],
            $totals['code_examples'],
            $totals['exercises'],
            $totals['quiz'],
            $totals['cards'],
            $totals['concepts'],
        ));

        if ($approved > 0) {
            $this->info("Approved {$approved} entities.");

            if (! $dryRun) {
                Artisan::call('cache:clear');
                $this->line('Cache cleared.');
            }
        }

        if ($errors > 0) {
            $this->error("{$errors} lesson(s) failed.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function approveLesson(ContentApprover $approver, Lesson $lesson, User $admin): int
    {
        $count = 0;

        $approver->approve('lesson', $lesson->id, $admin);
        $count++;

        foreach (Exercise::query()->where('lesson_id', $lesson->id)->pluck('id') as $id) {
            $approver->approve('exercise', $id, $admin);
            $count++;
        }

        foreach (QuizQuestion::query()->where('lesson_id', $lesson->id)->pluck('id') as $id) {
            $approver->approve('quiz_question', $id, $admin);
            $count++;
        }

        foreach (Flashcard::query()->where('lesson_id', $lesson->id)->pluck('id') as $id) {
            $approver->approve('flashcard', $id, $admin);
            $count++;
        }

        foreach (CodeExample::query()->where('lesson_id', $lesson->id)->pluck('id') as $id) {
            $approver->approve('code_example', $id, $admin);
            $count++;
        }

        return $count;
    }
}
