<?php

namespace App\Livewire\Learn;

use App\Enums\MasteryLevel;
use App\Enums\ProgressState;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\FlashcardReview;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\RecallAttempt;
use App\Models\Stage;
use App\Models\User;
use App\Services\Learning\EvidenceBuilder;
use App\Services\Learning\GateEvaluator;
use App\Services\Learning\GateEvidence;
use App\Services\Learning\GateResult;
use App\Services\Learning\ReviewScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The dashboard (docs/08 screen 1): weighted progress ring, 12 stage
 * chips with gate locks, due-today count, weak concepts, recent activity
 * and streak.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(
        EvidenceBuilder $evidenceBuilder,
        GateEvaluator $evaluator,
        ReviewScheduler $scheduler,
    ): View {
        $user = $this->user();

        $evidence = $evidenceBuilder->forUser($user->id);

        $scheduler->syncDue($user);

        return view('livewire.learn.dashboard', [
            'progress' => $this->weightedProgress($user),
            'chips' => $this->stageChips($evidence, $evaluator),
            'dueCount' => $scheduler->dueItems($user)->count(),
            'nextDueAt' => $scheduler->nextDueAt($user),
            'weak' => $this->weakConcepts($user),
            'activity' => $this->recentActivity($user),
            'streak' => $this->streak($user),
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Weighted overall progress (docs/08 screen 1): lessons 20%,
     * concepts mastered 40%, exercises 30%, quizzes 10%.
     *
     * @return array{pct: int, lessonsPct: int, lessonsRead: int, lessonsTotal: int, conceptsPct: int, conceptsMastered: int, conceptsTotal: int, exercisesPct: int, exercisesCorrect: int, exercisesTotal: int, quizzesPct: int, quizzesAttempted: int, quizzesTotal: int}
     */
    private function weightedProgress(User $user): array
    {
        $lessonsTotal = Lesson::query()->published()->count();
        $readIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('state', $this->readStateValues())
            ->pluck('lesson_id');
        $lessonsRead = $lessonsTotal > 0
            ? Lesson::query()->published()->whereIn('id', $readIds)->count()
            : 0;
        $lessonsPct = $lessonsTotal > 0 ? (int) round($lessonsRead * 100 / $lessonsTotal) : 0;

        $conceptsTotal = Concept::query()->count();
        $conceptsMastered = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->where('level', MasteryLevel::Mastered->value)
            ->count();
        $conceptsPct = $conceptsTotal > 0 ? (int) round($conceptsMastered * 100 / $conceptsTotal) : 0;

        $exercisesTotal = Exercise::query()->published()->count();
        $exercisesCorrect = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->where('result', 'correct')
            ->distinct()
            ->count('exercise_id');
        $exercisesPct = $exercisesTotal > 0 ? (int) round($exercisesCorrect * 100 / $exercisesTotal) : 0;

        $quizzesTotal = Lesson::query()->published()->whereHas('quizQuestions')->count();
        $quizzesAttempted = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->count('lesson_id');
        $quizzesPct = $quizzesTotal > 0 ? (int) round($quizzesAttempted * 100 / $quizzesTotal) : 0;

        return [
            'pct' => (int) round(0.2 * $lessonsPct + 0.4 * $conceptsPct + 0.3 * $exercisesPct + 0.1 * $quizzesPct),
            'lessonsPct' => $lessonsPct,
            'lessonsRead' => $lessonsRead,
            'lessonsTotal' => $lessonsTotal,
            'conceptsPct' => $conceptsPct,
            'conceptsMastered' => $conceptsMastered,
            'conceptsTotal' => $conceptsTotal,
            'exercisesPct' => $exercisesPct,
            'exercisesCorrect' => $exercisesCorrect,
            'exercisesTotal' => $exercisesTotal,
            'quizzesPct' => $quizzesPct,
            'quizzesAttempted' => $quizzesAttempted,
            'quizzesTotal' => $quizzesTotal,
        ];
    }

    /**
     * The 12 stage chips: first unlocked stage is "current".
     *
     * @return list<array{stage: Stage, locked: bool, current: bool, gate: GateResult}>
     */
    private function stageChips(GateEvidence $evidence, GateEvaluator $evaluator): array
    {
        $chips = [];
        $currentId = null;

        foreach (Stage::query()->orderBy('number')->get() as $stage) {
            $result = $evaluator->evaluate($stage->gate_rules, $evidence);
            $locked = $result->isGated() && ! $result->passed();

            if ($currentId === null && ! $locked) {
                $currentId = $stage->id;
            }

            $chips[] = [
                'stage' => $stage,
                'locked' => $locked,
                'current' => false,
                'gate' => $result,
            ];
        }

        foreach ($chips as $index => $chip) {
            $chips[$index]['current'] = $chip['stage']->id === $currentId;
        }

        return $chips;
    }

    /**
     * Top 5 weak concepts: has attempts but level below practicing.
     *
     * @return list<array{concept: Concept, level: MasteryLevel, attempts: int}>
     */
    private function weakConcepts(User $user): array
    {
        /** @var array<int, int> $attempts */
        $attempts = [];

        $countRows = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->join('exercises', 'exercises.id', '=', 'exercise_attempts.exercise_id')
            ->whereNotNull('exercises.concept_id')
            ->groupBy('exercises.concept_id')
            ->selectRaw('exercises.concept_id as cid, COUNT(*) as attempts')
            ->toBase()
            ->get();

        foreach ($countRows as $row) {
            $attempts[(int) $row->cid] = (int) $row->attempts;
        }

        if ($attempts === []) {
            return [];
        }

        $mastery = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('concept_id');

        $rows = [];

        foreach (Concept::query()->whereIn('id', array_keys($attempts))->get(['id', 'slug', 'name']) as $concept) {
            $level = $mastery->get($concept->id);
            $levelValue = $level === null ? MasteryLevel::Unseen : $level->level;

            if ($levelValue->rank() >= MasteryLevel::Practicing->rank()) {
                continue;
            }

            $rows[] = [
                'concept' => $concept,
                'level' => $levelValue,
                'attempts' => $attempts[$concept->id] ?? 0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['level']->rank() <=> $b['level']->rank()
            ?: $b['attempts'] <=> $a['attempts']);

        return array_slice($rows, 0, 5);
    }

    /**
     * Last 10 attempts, quizzes and card reviews, newest first.
     *
     * @return list<array{kind: string, label: string, detail: string, at: CarbonImmutable, url: string|null}>
     */
    private function recentActivity(User $user): array
    {
        $items = [];

        $exerciseTitles = Exercise::query()
            ->whereIn(
                'id',
                ExerciseAttempt::query()->where('user_id', $user->id)->distinct()->pluck('exercise_id'),
            )
            ->pluck('prompt', 'id');

        foreach (ExerciseAttempt::query()->where('user_id', $user->id)->latest('created_at')->limit(8)->get() as $attempt) {
            $items[] = [
                'kind' => 'exercise',
                'label' => $attempt->result->label(),
                'detail' => mb_substr((string) ($exerciseTitles[$attempt->exercise_id] ?? 'Exercise'), 0, 70),
                'at' => $attempt->created_at,
                'url' => null,
            ];
        }

        $lessonSlugs = Lesson::query()
            ->whereIn(
                'id',
                QuizAttempt::query()->where('user_id', $user->id)->distinct()->pluck('lesson_id'),
            )
            ->pluck('slug', 'id');

        foreach (QuizAttempt::query()->where('user_id', $user->id)->latest('created_at')->limit(8)->get() as $attempt) {
            $slug = $lessonSlugs[$attempt->lesson_id] ?? null;

            $items[] = [
                'kind' => 'quiz',
                'label' => 'Quiz '.(int) round((float) $attempt->score).'%',
                'detail' => $slug === null ? 'Quiz' : 'Lesson '.(string) $slug,
                'at' => $attempt->created_at,
                'url' => $slug === null ? null : route('quiz.show', $slug),
            ];
        }

        foreach (FlashcardReview::query()->where('user_id', $user->id)->whereNotNull('reviewed_at')->latest('reviewed_at')->limit(8)->get() as $review) {
            $items[] = [
                'kind' => 'card',
                'label' => 'Card reviewed',
                'detail' => 'Interval '.$review->interval_days.'d',
                'at' => $review->reviewed_at,
                'url' => route('flashcards'),
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['at']->getTimestamp() <=> $a['at']->getTimestamp());

        return array_slice($items, 0, 10);
    }

    /**
     * Consecutive days ending today (or yesterday) with at least one action.
     */
    private function streak(User $user): int
    {
        $days = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('created_at')
            ->merge(QuizAttempt::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->pluck('created_at'))
            ->merge(FlashcardReview::query()
                ->where('user_id', $user->id)
                ->whereNotNull('reviewed_at')
                ->where('reviewed_at', '>=', now()->subDays(90))
                ->pluck('reviewed_at'))
            ->merge(RecallAttempt::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->pluck('created_at'));

        $dates = $days
            ->filter()
            ->map(fn ($day): string => Carbon::parse($day)->toDateString())
            ->unique()
            ->all();

        if ($dates === []) {
            return 0;
        }

        $cursor = Carbon::today();

        if (! in_array($cursor->toDateString(), $dates, true)) {
            $cursor = $cursor->subDay();

            if (! in_array($cursor->toDateString(), $dates, true)) {
                return 0;
            }
        }

        $streak = 0;

        while (in_array($cursor->toDateString(), $dates, true)) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * @return list<string>
     */
    private function readStateValues(): array
    {
        return array_values(array_map(
            fn (ProgressState $state): string => $state->value,
            array_filter(
                ProgressState::cases(),
                fn (ProgressState $state): bool => $state->rank() >= ProgressState::Read->rank(),
            ),
        ));
    }
}
