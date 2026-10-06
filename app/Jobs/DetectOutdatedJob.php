<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Services\Ai\Generators\OutdatedGenerator;
use App\Services\Content\ModernPanelBuilder;
use App\Services\Content\PipelineWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: lesson content vs the book's year -> is_outdated flag plus
 * the BOOK/MODERN/WHY panel block (docs/10 DetectOutdatedJob).
 */
class DetectOutdatedJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $lessonId) {}

    public function handle(
        OutdatedGenerator $generator,
        ModernPanelBuilder $panel,
        PipelineWriter $writer,
    ): void {
        $lesson = Lesson::query()->findOrFail($this->lessonId);

        $draft = $generator->generate($lesson);

        if ($draft === null) {
            return;
        }

        $lesson->forceFill(['is_outdated' => $draft->isOutdated])->save();

        $writer->upsertPanel($lesson, $panel->fromDraft($draft));
    }
}
