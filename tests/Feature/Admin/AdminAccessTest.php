<?php

use App\Models\User;

it('blocks guests from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('blocks learners from the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('allows admins into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('serves every learner navigation destination', function (string $uri) {
    $this->actingAs(User::factory()->create())
        ->get($uri)
        ->assertOk();
})->with([
    '/dashboard',
    '/path',
    '/book',
    '/practice',
    '/quiz',
    '/debug',
    '/flashcards',
    '/revision',
    '/skills',
    '/interview',
    '/projects',
    '/tutor',
    '/search',
]);
