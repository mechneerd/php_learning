<?php

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Livewire\Learn\LessonView;
use App\Models\CodeExample;
use App\Models\Diagram;
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
        ->assertSet('mode', 'practice')
        ->assertSee('Phase 4')
        ->call('setMode', 'teach')
        ->assertSee('Phase 6')
        ->call('setRailTab', 'tutor')
        ->assertSee('never calls the AI')
        ->call('setMode', 'invented-mode')
        ->assertSet('mode', 'teach');
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
