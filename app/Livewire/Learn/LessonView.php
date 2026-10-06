<?php

namespace App\Livewire\Learn;

use App\Enums\ContentStatus;
use App\Enums\ProgressState;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\Content\LessonBuilder;
use App\Support\LaravelBridge;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lesson')]
class LessonView extends Component
{
    public const TICK_SECONDS = 15;

    public int $lessonId = 0;

    public string $mode = 'read';

    public string $railTab = 'progress';

    /**
     * @var list<string>
     */
    private const MODES = ['read', 'teach', 'practice', 'quiz', 'debug'];

    /**
     * @var array<string, int>
     */
    private const MODE_PHASE = [
        'teach' => 6,
        'debug' => 9,
    ];

    /**
     * @var list<string>
     */
    private const RAIL_TABS = ['progress', 'concepts', 'notes', 'tutor'];

    public function mount(Lesson $lesson): void
    {
        abort_unless(
            $lesson->status === ContentStatus::Published || auth()->user()?->isAdmin(),
            404,
        );

        $this->lessonId = $lesson->id;

        LessonProgress::firstOrCreate(
            ['user_id' => auth()->id(), 'lesson_id' => $lesson->id],
            ['state' => ProgressState::Opened, 'opened_at' => now(), 'last_at' => now()],
        );
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            return;
        }

        if ($mode === 'practice' || $mode === 'quiz') {
            $slug = Lesson::query()->whereKey($this->lessonId)->value('slug');
            $route = $mode === 'practice' ? 'practice.show' : 'quiz.show';
            $this->redirect(route($route, $slug));

            return;
        }

        $this->mode = $mode;
    }

    public function setRailTab(string $tab): void
    {
        if (in_array($tab, self::RAIL_TABS, true)) {
            $this->railTab = $tab;
        }
    }

    /**
     * Focused-reading heartbeat dispatched from the page every 15 seconds
     * while the tab is visible.
     */
    #[On('lesson-tick')]
    public function tick(): void
    {
        $progress = LessonProgress::firstOrCreate(
            ['user_id' => auth()->id(), 'lesson_id' => $this->lessonId],
            ['state' => ProgressState::Opened, 'opened_at' => now(), 'last_at' => now()],
        );

        $progress->recordFocus(self::TICK_SECONDS);

        if ($progress->active_seconds >= 60) {
            $progress->upgradeTo(ProgressState::Read);
        }
    }

    public function render(LessonBuilder $builder): View
    {
        $lesson = Lesson::query()->findOrFail($this->lessonId);

        $payload = $builder->build($lesson);
        $tree = $builder->navTree();
        $flat = $this->flatten($tree);
        $position = array_search($lesson->id, array_column($flat, 'id'), true);

        return view('livewire.learn.lesson-view', [
            'lesson' => $payload['lesson'],
            'lessonModel' => $lesson,
            'blocks' => $payload['blocks'],
            'codeExamples' => $payload['code_examples'],
            'diagrams' => $payload['diagrams'],
            'tree' => $tree,
            'progress' => LessonProgress::query()
                ->where('user_id', auth()->id())
                ->where('lesson_id', $lesson->id)
                ->first(),
            'prev' => $position > 0 ? $flat[$position - 1] : null,
            'next' => $position !== false && isset($flat[$position + 1]) ? $flat[$position + 1] : null,
            'modePhase' => self::MODE_PHASE[$this->mode] ?? null,
            'bridgeRows' => LaravelBridge::forStage($lesson->stage->number ?? -1),
        ]);
    }

    /**
     * Flat reading order for prev/next navigation.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array{id: int, slug: string, title: string}>
     */
    private function flatten(array $tree): array
    {
        $flat = [];

        foreach ($tree as $chapter) {
            foreach ($chapter['sections'] as $section) {
                foreach ($section['lessons'] as $lesson) {
                    $flat[] = $lesson;
                }
            }

            foreach ($chapter['lessons'] as $lesson) {
                $flat[] = $lesson;
            }
        }

        return $flat;
    }
}
