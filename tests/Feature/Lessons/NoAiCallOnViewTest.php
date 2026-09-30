<?php

use App\Livewire\Learn\LessonView;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\NullAiClient;
use Livewire\Livewire;
use Tests\Support\LessonFixture;

it('resolves a null AI client by default', function () {
    expect(app(AiClient::class))->toBeInstanceOf(NullAiClient::class);
});

it('never calls the AI while a lesson is being read', function () {
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

    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $this->actingAs($user)
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk();

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->call('setRailTab', 'tutor')
        ->call('setMode', 'teach')
        ->assertSee('Arrives in Phase 6');

    expect($spy->calls)->toBe(0);
});
