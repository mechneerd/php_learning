<?php

use App\Models\Chapter;
use App\Models\Stage;

it('plans every chapter of an en-dash range', function () {
    Stage::factory()->create(['number' => 1, 'slug' => 'orientation', 'subtitle' => 'Book chapters 1–2']);
    Chapter::factory()->create(['number' => 1]);
    Chapter::factory()->create(['number' => 2]);

    $this->artisan('content:generate', ['stage' => '1', '--dry-run' => true])
        ->expectsOutputToContain('Ch. 1')
        ->expectsOutputToContain('Ch. 2')
        ->assertExitCode(0);
});

it('still plans ascii-hyphen ranges', function () {
    Stage::factory()->create(['number' => 3, 'slug' => 'object-tools-design', 'subtitle' => 'Book chapters 5-6']);
    Chapter::factory()->create(['number' => 5]);
    Chapter::factory()->create(['number' => 6]);

    $this->artisan('content:generate', ['stage' => '3', '--dry-run' => true])
        ->expectsOutputToContain('Ch. 5')
        ->expectsOutputToContain('Ch. 6')
        ->assertExitCode(0);
});

it('plans a single chapter subtitle', function () {
    Stage::factory()->create(['number' => 5, 'slug' => 'creational-patterns', 'subtitle' => 'Book chapter 9']);
    Chapter::factory()->create(['number' => 9]);

    $this->artisan('content:generate', ['stage' => 'creational-patterns', '--dry-run' => true])
        ->expectsOutputToContain('Ch. 9')
        ->assertExitCode(0);
});

it('fails when the subtitle has no chapter reference', function () {
    Stage::factory()->create(['number' => 1, 'slug' => 'orientation', 'subtitle' => 'No book chapters here']);

    $this->artisan('content:generate', ['stage' => '1', '--dry-run' => true])
        ->assertExitCode(1);
});
