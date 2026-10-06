<?php

namespace App\Livewire\Learn;

use App\Enums\AiMode;
use App\Enums\AiRole;
use App\Enums\AttemptResult;
use App\Enums\TutorIntent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\BudgetExceeded;
use App\Services\Ai\ContextAssembler;
use App\Services\Ai\HintPolicy;
use App\Services\Ai\PromptRunner;
use App\Services\Ai\Prompts\TutorPrompt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * AI tutor (docs/08 screen 12, docs/10 Pipeline B): context-aware chat with
 * the hint-ladder policy, quick actions and a usage footer. Mounted both on
 * /tutor and in the lesson rail's Tutor tab. Reading never calls the AI -
 * only send()/quickAction() do.
 */
#[Title('AI Tutor')]
class TutorChat extends Component
{
    public ?Lesson $lesson = null;

    /**
     * @var list<array{role: string, content: string, meta: array<string, mixed>}>
     */
    public array $messages = [];

    public string $input = '';

    public ?string $notice = null;

    public int $tokensToday = 0;

    public int $hintLevel = 0;

    public function mount(?Lesson $lesson = null): void
    {
        $this->lesson = $lesson;

        $conversation = $this->existingConversation();

        if ($conversation === null) {
            return;
        }

        $loaded = [];

        foreach ($conversation->messages()->latest('id')->limit(50)->get()->reverse() as $message) {
            $loaded[] = [
                'role' => $message->role->value,
                'content' => $message->content,
                'meta' => $message->meta ?? [],
            ];
        }

        $this->messages = $loaded;
        $this->hintLevel = (int) (($conversation->context ?? [])['hint_level'] ?? 0);
        $this->tokensToday = $this->tokensUsedToday();
    }

    public function send(
        PromptRunner $runner,
        ContextAssembler $assembler,
        TutorPrompt $prompt,
        HintPolicy $policy,
    ): void {
        $this->deliver(trim($this->input), $runner, $assembler, $prompt, $policy);
    }

    public function quickAction(
        string $action,
        PromptRunner $runner,
        ContextAssembler $assembler,
        TutorPrompt $prompt,
        HintPolicy $policy,
    ): void {
        $question = match ($action) {
            'explain_simpler' => 'Explain this simpler, like I am new to this.',
            'example' => 'Give me another example.',
            'quiz' => 'Quiz me on this.',
            'exercise' => 'Give me an exercise to practice this.',
            'hint' => 'I am stuck - give me a hint.',
            default => '',
        };

        if ($question === '') {
            return;
        }

        $this->input = $question;

        $this->deliver($question, $runner, $assembler, $prompt, $policy);
    }

    public function clear(): void
    {
        $conversation = $this->existingConversation();

        if ($conversation !== null) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        $this->messages = [];
        $this->notice = null;
        $this->hintLevel = 0;
        $this->input = '';
    }

    public function render(): View
    {
        return view('livewire.learn.tutor-chat', [
            'anchored' => $this->lesson?->citation(),
            'budget' => (int) config('ai.daily_token_budget'),
        ]);
    }

    private function deliver(
        string $question,
        PromptRunner $runner,
        ContextAssembler $assembler,
        TutorPrompt $prompt,
        HintPolicy $policy,
    ): void {
        $this->notice = null;

        if ($question === '') {
            $this->notice = 'Type a question first.';

            return;
        }

        $user = $this->user();

        if (! $this->allowMessage($user)) {
            return;
        }

        $conversation = $this->conversationFor($user);
        $intent = TutorIntent::classify($question);
        $givenLevels = $this->givenLevels($conversation);
        $failed = $this->attemptQuery($user)->where('result', AttemptResult::Incorrect->value)->count();
        $attempts = $this->attemptQuery($user)->count();
        $createdAt = $conversation->created_at?->getTimestamp() ?? now()->getTimestamp();
        $seconds = max(0, now()->getTimestamp() - $createdAt);
        $solutionAllowed = $policy->solutionAllowed($failed, $givenLevels, $seconds);
        $nextLevel = $policy->nextHintLevel($attempts, $givenLevels);

        $handsOutHint = $intent === TutorIntent::Hint
            || ($intent === TutorIntent::Solution && ! $solutionAllowed);

        $hintLevel = $handsOutHint ? $nextLevel : max($this->hintLevel, 1);

        $context = $assembler->assemble($user, $this->lesson, $this->lesson !== null ? AiMode::Teach->value : AiMode::Tutor->value);
        $context += [
            'intent' => $intent->value,
            'solution_allowed' => $solutionAllowed,
            'hint_level' => $hintLevel,
            'question' => $question,
            'lesson_title' => $this->lesson?->title,
            'lesson_citation' => $this->lesson?->citation(),
            'exercise_prompt' => $this->lesson?->exercises()->value('prompt'),
        ];

        try {
            $result = $runner->run(
                $user,
                'tutor',
                $prompt->system($intent, $solutionAllowed, $hintLevel),
                $prompt->user($question, $context),
                $context,
            );
        } catch (BudgetExceeded $exceeded) {
            $this->notice = 'Daily AI budget spent ('.$exceeded->used.'/'.$exceeded->budget
                .' tokens). Try again tomorrow.';

            return;
        }

        $sources = array_filter([$this->lesson?->citation()]);

        $conversation->messages()->create([
            'role' => AiRole::User,
            'content' => $question,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => 0,
            'meta' => ['intent' => $intent->value],
        ]);

        $assistant = $conversation->messages()->create([
            'role' => AiRole::Assistant,
            'content' => $result->content,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'meta' => [
                'intent' => $intent->value,
                'sources' => $sources,
                'solution_given' => $intent === TutorIntent::Solution && $solutionAllowed,
            ],
        ]);

        if ($handsOutHint) {
            $this->hintLevel = $nextLevel;
            $conversation->update([
                'context' => array_merge($conversation->context ?? [], ['hint_level' => $this->hintLevel]),
            ]);
        }

        $this->messages = [
            ...$this->messages,
            ['role' => AiRole::User->value, 'content' => $question, 'meta' => ['intent' => $intent->value]],
            ['role' => AiRole::Assistant->value, 'content' => $result->content, 'meta' => $assistant->meta ?? []],
        ];

        $this->input = '';
        $this->tokensToday = $this->tokensUsedToday();
    }

    private function allowMessage(User $user): bool
    {
        $key = 'tutor:'.$user->id;
        $max = (int) config('ai.tutor_messages_per_10_minutes');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->notice = 'You are sending messages too quickly - wait '
                .RateLimiter::availableIn($key).' seconds.';

            return false;
        }

        RateLimiter::hit($key, 600);

        return true;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function existingConversation(): ?AiConversation
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $this->conversationQuery($user)->latest('id')->first();
    }

    private function conversationFor(User $user): AiConversation
    {
        return $this->conversationQuery($user)->firstOrCreate([
            'user_id' => $user->id,
            'mode' => $this->lesson !== null ? AiMode::Teach : AiMode::Tutor,
            'lesson_id' => $this->lesson?->id,
        ], [
            'context' => [],
        ]);
    }

    /**
     * @return Builder<AiConversation>
     */
    private function conversationQuery(User $user): Builder
    {
        return AiConversation::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $this->lesson?->id);
    }

    /**
     * @return list<int>
     */
    private function givenLevels(AiConversation $conversation): array
    {
        $level = (int) (($conversation->context ?? [])['hint_level'] ?? 0);

        if ($level < 1) {
            return [];
        }

        return range(1, $level);
    }

    /**
     * @return Builder<ExerciseAttempt>
     */
    private function attemptQuery(User $user): Builder
    {
        $query = ExerciseAttempt::query()->where('user_id', $user->id);

        if ($this->lesson !== null) {
            $query->whereHas('exercise', fn (Builder $exercise) => $exercise->where('lesson_id', $this->lesson->id));
        }

        return $query;
    }

    private function tokensUsedToday(): int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return 0;
        }

        $row = AiMessage::query()
            ->whereHas('conversation', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where('created_at', '>=', today())
            ->selectRaw('COALESCE(SUM(tokens_in + tokens_out), 0) as total')
            ->toBase()
            ->first();

        return (int) ($row->total ?? 0);
    }
}
