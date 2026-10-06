<?php

use App\Livewire\Learn\TutorChat;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->guard()->logout();

    $this->get(route('tutor'))->assertRedirect(route('login'));
});

test('serves the tutor page without calling the AI', function () {
    $spy = new class implements AiClient
    {
        public int $calls = 0;

        public function complete(string $prompt, array $context = []): string
        {
            $this->calls++;

            return 'should not happen';
        }
    };

    app()->instance(AiClient::class, $spy);

    $this->get(route('tutor'))
        ->assertOk()
        ->assertSee('General tutor')
        ->assertSee('Explain simpler');

    Livewire::test(TutorChat::class)->assertSee('Hint only');

    expect($spy->calls)->toBe(0);
});

test('persists both sides of the conversation with token accounting', function () {
    Livewire::test(TutorChat::class)
        ->set('input', 'How do constructors work in PHP?')
        ->call('send')
        ->assertSet('notice', null)
        ->assertSet('messages.0.role', 'user')
        ->assertSet('messages.1.role', 'assistant');

    $conversation = AiConversation::query()->sole();

    expect($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->messages()->count())->toBe(2);

    $assistant = $conversation->messages()->where('role', 'assistant')->sole();

    expect($assistant->tokens_out)->toBeGreaterThan(0)
        ->and($assistant->meta)->toHaveKey('intent');
});

test('rate limits messages per ten minutes', function () {
    config(['ai.tutor_messages_per_10_minutes' => 3]);

    foreach (range(1, 3) as $ignored) {
        RateLimiter::hit('tutor:'.$this->user->id, 600);
    }

    Livewire::test(TutorChat::class)
        ->set('input', 'Please explain something to me right now')
        ->call('send')
        ->assertSet('notice', fn (?string $notice): bool => $notice !== null && str_contains($notice, 'too quickly'));

    expect(AiMessage::query()->count())->toBe(0);
});

test('stops at the daily token budget', function () {
    config(['ai.daily_token_budget' => 10]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->user->id,
        'mode' => 'tutor',
        'context' => [],
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Earlier question',
        'tokens_in' => 500,
        'tokens_out' => 0,
    ]);

    Livewire::test(TutorChat::class)
        ->set('input', 'And how does that work exactly then?')
        ->call('send')
        ->assertSet('notice', fn (?string $notice): bool => $notice !== null && str_contains($notice, 'budget'));

    expect(AiMessage::query()->where('role', 'user')->count())->toBe(1);
});

test('quick actions send a canned question', function () {
    Livewire::test(TutorChat::class)
        ->call('quickAction', 'hint')
        ->assertSet('messages.0.content', 'I am stuck - give me a hint.')
        ->assertSet('messages.1.role', 'assistant');

    expect(AiMessage::query()->count())->toBe(2);
});
