<?php

namespace App\Livewire\Learn;

use App\Http\Requests\SubmitQuizAnswerRequest;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Services\Learning\QuizScorer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class QuizRunner extends Component
{
    public ?int $lessonId = null;

    public int $index = 0;

    /**
     * Buffered answers, written to the DB when the quiz finishes.
     *
     * @var list<array{question_id: int, option_id: int|null, answer_text: string|null, correct: bool, concept_slug: string|null}>
     */
    public array $answered = [];

    public int $startedAt = 0;

    public ?bool $lastCorrect = null;

    public string $lastFeedback = '';

    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public ?string $option_id = null;

    public ?string $answer_text = null;

    public function mount(?Lesson $lesson = null): void
    {
        $this->lessonId = $lesson->id ?? $this->defaultLessonId();
        $this->startedAt = time();
    }

    public function submit(): void
    {
        $this->validate(SubmitQuizAnswerRequest::formRules());

        $question = $this->currentQuestion();

        if ($question === null || $this->alreadyAnswered($question->id)) {
            return;
        }

        $optionId = $this->option_id !== null ? (int) $this->option_id : null;
        $answerText = $this->answer_text;

        $correct = $this->isCorrect($question, $optionId, $answerText);

        $this->answered[] = [
            'question_id' => $question->id,
            'option_id' => $optionId,
            'answer_text' => $answerText,
            'correct' => $correct,
            'concept_slug' => $question->concept?->slug,
        ];

        $this->lastCorrect = $correct;
        $this->lastFeedback = (string) ($question->explanation ?? '');

        $this->resetValidation();
        $this->option_id = null;
        $this->answer_text = null;

        if (count($this->answered) === count($this->questions())) {
            $this->finish();
        }
    }

    public function next(): void
    {
        if ($this->lastCorrect === null) {
            return;
        }

        if ($this->index < count($this->questions()) - 1) {
            $this->index++;
        }

        $this->lastCorrect = null;
        $this->lastFeedback = '';
        $this->option_id = null;
        $this->answer_text = null;
    }

    public function retry(): void
    {
        $this->answered = [];
        $this->report = null;
        $this->index = 0;
        $this->lastCorrect = null;
        $this->lastFeedback = '';
        $this->option_id = null;
        $this->answer_text = null;
        $this->startedAt = time();
    }

    public function render(): View
    {
        $questions = $this->questions();
        $current = $questions[$this->index] ?? null;

        return view('livewire.learn.quiz', [
            'lesson' => Lesson::query()->find($this->lessonId),
            'questions' => $questions,
            'current' => $current,
            'total' => count($questions),
            'answeredCount' => count($this->answered),
        ]);
    }

    private function finish(): void
    {
        $questionsById = collect($this->questions())->keyBy('id');

        $scored = array_map(function (array $answer) use ($questionsById): array {
            $question = $questionsById[$answer['question_id']] ?? null;

            return [
                'correct' => $answer['correct'],
                'concept_slug' => $question?->concept?->slug,
            ];
        }, $this->answered);

        $result = app(QuizScorer::class)->score($scored);

        $bestBefore = QuizAttempt::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $this->lessonId)
            ->max('score');

        $attempt = QuizAttempt::query()->create([
            'user_id' => Auth::id(),
            'lesson_id' => $this->lessonId,
            'score' => $result->score,
            'total' => $result->total,
            'correct_count' => $result->correct,
            'weak_concepts' => $result->weakConcepts,
            'duration_sec' => max(0, time() - $this->startedAt),
        ]);

        foreach ($this->answered as $answer) {
            $attempt->answers()->create([
                'question_id' => $answer['question_id'],
                'option_id' => $answer['option_id'],
                'answer_text' => $answer['answer_text'],
                'is_correct' => $answer['correct'],
            ]);
        }

        $review = [];
        foreach ($this->answered as $answer) {
            $question = $questionsById[$answer['question_id']] ?? null;
            if ($question === null) {
                continue;
            }

            $given = $answer['option_id'] !== null
                ? (string) $question->options->firstWhere('id', $answer['option_id'])?->text
                : $answer['answer_text'];

            $correctOption = $question->options->firstWhere('is_correct', true);

            $review[] = [
                'stem' => $question->stem,
                'given' => $given,
                'correct_answer' => $correctOption->text ?? $answer['answer_text'],
                'correct' => $answer['correct'],
                'explanation' => $question->explanation,
            ];
        }

        $this->report = [
            'score' => $result->score,
            'correct' => $result->correct,
            'total' => $result->total,
            'weak_concepts' => $result->weakConcepts,
            'duration_sec' => max(0, time() - $this->startedAt),
            'is_best' => $result->score >= (float) ($bestBefore ?? 0),
            'review' => $review,
        ];
    }

    /** @return array<int, QuizQuestion> */
    private function questions(): array
    {
        if ($this->lessonId === null) {
            return [];
        }

        return QuizQuestion::query()
            ->published()
            ->where('lesson_id', $this->lessonId)
            ->with('options', 'concept:id,slug,name')
            ->orderBy('ord')
            ->get()
            ->all();
    }

    private function currentQuestion(): ?QuizQuestion
    {
        return $this->questions()[$this->index] ?? null;
    }

    private function alreadyAnswered(int $questionId): bool
    {
        foreach ($this->answered as $answer) {
            if ($answer['question_id'] === $questionId) {
                return true;
            }
        }

        return false;
    }

    private function isCorrect(QuizQuestion $question, ?int $optionId, ?string $answerText): bool
    {
        if ($optionId !== null) {
            $option = $question->options->firstWhere('id', $optionId);

            return $option !== null && $option->is_correct;
        }

        $given = mb_strtolower(trim((string) $answerText));

        if ($given === '') {
            return false;
        }

        foreach ($question->options as $option) {
            if ($option->is_correct && mb_strtolower(trim($option->text)) === $given) {
                return true;
            }
        }

        return false;
    }

    private function defaultLessonId(): ?int
    {
        $id = Lesson::query()
            ->published()
            ->whereHas('quizQuestions', fn ($query) => $query->published())
            ->orderBy('ord')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
