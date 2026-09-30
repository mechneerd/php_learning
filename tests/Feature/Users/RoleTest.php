<?php

use App\Enums\UserRole;
use App\Models\User;

it('defaults new users to the learner role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Learner)
        ->and($user->isAdmin())->toBeFalse();
});

it('recognises admin users', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue();
});
