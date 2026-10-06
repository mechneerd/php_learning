<?php

use App\Models\User;
use Tests\Support\LessonFixture;

it('renders the mobile bottom sheet rail with a dialog and tab bar', function () {
    $lesson = LessonFixture::full();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('x-data="{ railSheet: false }"')
        ->toContain('role="dialog"')
        ->toContain('aria-label="Lesson context panel"')
        ->toContain('aria-label="Close panel"')
        ->toContain('aria-label="Lesson context"');
});

it('marks the active mode and rail tabs for assistive tech and scrolls mode tabs', function () {
    $lesson = LessonFixture::full();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('aria-pressed="true"')
        ->toContain('aria-selected="true"')
        ->toContain('overflow-x-auto')
        ->toContain('aria-label="Lesson modes"');
});

it('keeps the desktop rail hidden below large screens and pads for the tab bar', function () {
    $lesson = LessonFixture::full();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('hidden lg:block xl:sticky')
        ->toContain('pb-24')
        ->toContain('fixed inset-x-0 bottom-0 z-30');
});
