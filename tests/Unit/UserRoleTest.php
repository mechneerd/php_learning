<?php

use App\Enums\UserRole;

it('exposes role labels', function () {
    expect(UserRole::options())->toBe([
        'learner' => 'Learner',
        'admin' => 'Admin',
    ]);
});

it('has exactly two roles', function () {
    expect(UserRole::cases())->toHaveCount(2);
});
