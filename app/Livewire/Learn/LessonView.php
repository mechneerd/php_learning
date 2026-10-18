<?php

namespace App\Livewire\Learn;

use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\NoteKind;
use App\Enums\ProgressState;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Note;
use App\Models\User;
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

    public string $noteBody = '';

    public string $noteKind = 'note';

    /**
     * @var list<string>
     */
    private const MODES = ['read', 'teach', 'practice', 'quiz', 'debug'];

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
     * Save a personal note on this lesson (docs/08 screen 14: the rail's
     * Notes tab is the per-lesson composer behind /notes).
     */
    public function saveNote(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->validate([
            'noteBody' => ['required', 'string', 'max:2000'],
            'noteKind' => ['required', 'string', 'in:'.implode(',', array_map(
                static fn (NoteKind $kind): string => $kind->value,
                NoteKind::cases(),
            ))],
        ]);

        Note::query()->create([
            'user_id' => $user->id,
            'noteable_type' => Lesson::class,
            'noteable_id' => $this->lessonId,
            'kind' => NoteKind::from($this->noteKind),
            'body' => trim($this->noteBody),
        ]);

        $this->noteBody = '';
        $this->railTab = 'notes';
    }

    /**
     * Delete one of the current user's notes on this lesson.
     */
    public function deleteNote(int $noteId): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        Note::query()
            ->whereKey($noteId)
            ->where('user_id', $user->id)
            ->where('noteable_type', Lesson::class)
            ->where('noteable_id', $this->lessonId)
            ->delete();
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
            'debugExercises' => $this->mode === 'debug' ? Exercise::query()
                ->where('lesson_id', $lesson->id)
                ->whereIn('type', [ExerciseType::FindError, ExerciseType::Fix])
                ->published()
                ->orderBy('ord')
                ->get() : collect(),
            'bridgeRows' => LaravelBridge::forStage($lesson->stage->number ?? -1),
            'notes' => $this->railTab === 'notes' ? Note::query()
                ->where('user_id', auth()->id())
                ->where('noteable_type', Lesson::class)
                ->where('noteable_id', $lesson->id)
                ->orderByDesc('created_at')
                ->get() : collect(),
            'concepts' => $this->railTab === 'concepts' ? $lesson->concepts()
                ->orderBy('concepts.name')
                ->get(['concepts.id', 'concepts.slug', 'concepts.name', 'concepts.definition', 'concepts.is_core', 'lesson_concepts.role'])
                : collect(),
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
