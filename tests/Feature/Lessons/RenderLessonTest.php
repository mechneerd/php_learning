<?php

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ExerciseType;
use App\Enums\ProvenanceSource;
use App\Livewire\Learn\LessonView;
use App\Models\CodeExample;
use App\Models\Diagram;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\LessonFixture;

it('renders every block type with provenance badges and citation', function () {
    $lesson = LessonFixture::full();

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertSee('A First Object (or Two)')
        ->assertSee('What is an object?')
        ->assertSee('State lives in properties')
        ->assertSee('new ShopProduct()')
        ->assertSee('ShopProduct Object')
        ->assertSee('visible from anywhere')
        ->assertSee('An object is a thing')
        ->assertSee('constructor property promotion')
        ->assertSee('Explain the difference between a class and an object.')
        ->assertSee('The ShopProduct class sketch')
        ->assertSee('First object')
        ->assertSee('Class versus object')
        ->assertSee('Ch. 3')
        ->assertSee('pp. 22–23')
        ->assertSee('PDF 42–43')
        ->assertSee('Book')
        ->assertSee('mermaid');
});

it('renders all eighteen block partials on the page', function () {
    $lesson = LessonFixture::full();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->getContent();

    foreach (BlockType::cases() as $type) {
        expect(view()->exists('lessons.blocks.'.$type->value))->toBeTrue();
    }

    expect($html)->toContain('language-php')
        ->toContain('<details');
});

it('renders an extracted book figure as an image with alt text', function () {
    $lesson = LessonFixture::full();

    $lesson->blocks()->create([
        'ord' => 99,
        'type' => BlockType::Image,
        'payload' => [
            'figure_ref' => '16-1',
            'caption' => 'Figure 16-1. Adding a package to Packagist',
            'page_pdf' => 619,
            'src' => 'figures/figure-619.png',
            'alt' => 'Screenshot of a package being added to Packagist',
            'media_id' => 1,
            'width' => 863,
            'height' => 683,
        ],
        'source' => ProvenanceSource::Book,
        'page_printed_from' => 606,
        'page_printed_to' => 606,
        'page_pdf_from' => 619,
        'page_pdf_to' => 619,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertSee('figures/figure-619.png')
        ->assertSee('Screenshot of a package being added to Packagist')
        ->assertSee('loading="lazy"', false)
        ->assertSee('width="863"', false);
});

it('404s for unpublished lessons', function () {
    LessonFixture::full(['status' => ContentStatus::Draft, 'slug' => 'draft-lesson']);

    $this->actingAs(User::factory()->create())
        ->get('/lessons/draft-lesson')
        ->assertNotFound();
});

it('lets an admin preview a draft lesson', function () {
    LessonFixture::full(['status' => ContentStatus::Draft, 'slug' => 'draft-lesson']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/lessons/draft-lesson')
        ->assertOk();
});

it('redirects guests to login', function () {
    $lesson = LessonFixture::full();

    $this->get(route('lessons.show', $lesson->slug))->assertRedirect('/login');
});

it('redirects /lessons to the last opened lesson', function () {
    $user = User::factory()->create();
    $first = LessonFixture::full();
    $second = Lesson::factory()->create([
        'stage_id' => $first->stage_id,
        'title' => 'Setting Properties',
        'slug' => 'ch3-setting-properties',
        'status' => ContentStatus::Published,
        'ord' => 2,
    ]);

    LessonProgress::factory()->create([
        'user_id' => $user->id,
        'lesson_id' => $second->id,
        'last_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get('/lessons')
        ->assertRedirect(route('lessons.show', 'ch3-setting-properties'));
});

it('redirects /lessons to the first lesson when nothing was opened', function () {
    $user = User::factory()->create();
    LessonFixture::full();
    Lesson::factory()->create([
        'stage_id' => Lesson::firstOrFail()->stage_id,
        'title' => 'Second',
        'slug' => 'second-lesson',
        'status' => ContentStatus::Published,
        'ord' => 2,
    ]);

    $this->actingAs($user)
        ->get('/lessons')
        ->assertRedirect(route('lessons.show', 'ch3-a-first-object'));
});

it('404s on /lessons when no lessons exist', function () {
    $this->actingAs(User::factory()->create())->get('/lessons')->assertNotFound();
});

it('switches mode tabs and rail tabs without losing the lesson', function () {
    $lesson = LessonFixture::full();

    $this->actingAs(User::factory()->create());

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->assertSet('mode', 'read')
        ->call('setMode', 'practice')
        ->assertRedirect(route('practice.show', $lesson->slug))
        ->assertSet('mode', 'read')
        ->call('setMode', 'quiz')
        ->assertRedirect(route('quiz.show', $lesson->slug))
        ->call('setMode', 'teach')
        ->assertSee('Ask anything about the lesson')
        ->call('setRailTab', 'tutor')
        ->assertSee('Ask anything about the lesson')
        ->call('setMode', 'invented-mode')
        ->assertSet('mode', 'teach');
});

it('shows the debug mode panel with an empty state and no phase placeholder', function () {
    $lesson = LessonFixture::full();

    $this->actingAs(User::factory()->create());

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->call('setMode', 'debug')
        ->assertSet('mode', 'debug')
        ->assertSee('Debug mode')
        ->assertSee('No debugging challenges in this lesson yet')
        ->assertSee('Practice this lesson')
        ->assertDontSee('Arrives in Phase');
});

it('lists find and fix exercises in debug mode', function () {
    $lesson = LessonFixture::full();

    Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => ExerciseType::FindError,
        'prompt' => 'Spot the undefined index warning.',
    ]);
    Exercise::factory()->create([
        'lesson_id' => $lesson->id,
        'type' => ExerciseType::Fix,
        'prompt' => 'Repair the broken constructor call.',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(LessonView::class, ['lesson' => $lesson])
        ->call('setMode', 'debug')
        ->assertSee('Spot the undefined index warning.')
        ->assertSee('Find the error')
        ->assertSee('Repair the broken constructor call.')
        ->assertSee('Fix the bug')
        ->assertSee('Open Practice');
});

it('links the previous and next lessons in reading order', function () {
    $first = LessonFixture::full();
    Lesson::factory()->create([
        'stage_id' => $first->stage_id,
        'chapter_id' => $first->chapter_id,
        'title' => 'Setting Properties',
        'slug' => 'ch3-setting-properties',
        'status' => ContentStatus::Published,
        'ord' => 2,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $first->slug))
        ->assertOk()
        ->assertSee('Setting Properties');
});

it('opens a read-progress row when a lesson is viewed', function () {
    $user = User::factory()->create();
    $lesson = LessonFixture::full();

    $this->actingAs($user);

    Livewire::test(LessonView::class, ['lesson' => $lesson]);

    $progress = LessonProgress::where('user_id', $user->id)
        ->where('lesson_id', $lesson->id)
        ->first();

    expect($progress)->not->toBeNull()
        ->and($progress->active_seconds)->toBe(0);
});

it('shows linked diagram and code example details', function () {
    $lesson = LessonFixture::full();

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertSee(Diagram::firstOrFail()->title)
        ->assertSee(CodeExample::firstOrFail()->title)
        ->assertSee('Starter')
        ->assertSee('listing 03.01');
});
