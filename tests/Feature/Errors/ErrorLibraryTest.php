<?php

use App\Enums\ContentStatus;
use App\Enums\ErrorCategory;
use App\Models\ErrorPattern;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests are redirected to the login page', function () {
    auth()->guard()->logout();

    $this->get(route('errors'))->assertRedirect(route('login'));
});

test('lists published patterns grouped by category', function () {
    ErrorPattern::factory()->create([
        'slug' => 'unexpected-token',
        'name' => 'Parse error: unexpected token',
        'category' => ErrorCategory::Parse,
    ]);
    ErrorPattern::factory()->create([
        'slug' => 'argument-type-mismatch',
        'name' => 'TypeError: argument type mismatch',
        'category' => ErrorCategory::Type,
    ]);

    $this->get(route('errors'))
        ->assertOk()
        ->assertSee('Error Library')
        ->assertSee('Parse error: unexpected token')
        ->assertSee('TypeError: argument type mismatch')
        ->assertSee('Parse')
        ->assertSee('Type');
});

test('shows the empty state when nothing is published', function () {
    $this->get(route('errors'))
        ->assertOk()
        ->assertSee('No error patterns yet');
});

test('detail page renders the six-step flow', function () {
    $pattern = ErrorPattern::factory()->create([
        'slug' => 'unexpected-token',
        'name' => 'Parse error: unexpected token',
        'symptom' => 'PHP Parse error: syntax error, unexpected token "}" in report.php on line 14.',
        'cause' => 'The parser hit a token it did not accept.',
        'identify_steps' => ['Read the reported line', 'Run php -l report.php'],
        'fix_steps' => ['Add the missing semicolon', 'Re-run php -l'],
        'prevent_steps' => ['Run php -l in a pre-commit hook'],
        'practice_ref' => 'Practice: fix three parse errors blind.',
    ]);

    $this->get(route('errors.show', $pattern->slug))
        ->assertOk()
        ->assertSee('Parse error: unexpected token')
        ->assertSee('1 · What happened')
        ->assertSee('2 · Why')
        ->assertSee('3 · How to identify it')
        ->assertSee('4 · How to fix it')
        ->assertSee('5 · How to prevent it')
        ->assertSee('6 · Practice')
        ->assertSee('The parser hit a token it did not accept.')
        ->assertSee('Read the reported line')
        ->assertSee('Add the missing semicolon')
        ->assertSee('Run php -l in a pre-commit hook')
        ->assertSee('Practice: fix three parse errors blind.');
});

test('unpublished patterns are not visible', function () {
    $pattern = ErrorPattern::factory()->create(['status' => ContentStatus::Draft]);

    $this->get(route('errors.show', $pattern->slug))->assertNotFound();

    $this->get(route('errors'))
        ->assertOk()
        ->assertDontSee($pattern->name);
});
