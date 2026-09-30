<?php

use App\Livewire\Admin\Books\Index;
use App\Livewire\Learn\FlashcardSession;
use App\Livewire\Learn\LessonView;
use App\Livewire\Learn\PathView;
use App\Livewire\Learn\PracticeRunner;
use App\Livewire\Learn\QuizRunner;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('path', PathView::class)->name('path');
    Route::livewire('concepts/{concept:slug}', PathView::class)->name('concepts.show');
    Route::view('book', 'coming-soon', ['title' => __('Book'), 'phase' => 2, 'description' => __('Browse the book by part, chapter and section with original page numbers.')])->name('book');
    Route::livewire('practice', PracticeRunner::class)->name('practice');
    Route::livewire('quiz', QuizRunner::class)->name('quiz');
    Route::view('debug', 'coming-soon', ['title' => __('Debug Lab'), 'phase' => 9, 'description' => __('Find and fix broken PHP code.')])->name('debug');
    Route::livewire('flashcards', FlashcardSession::class)->name('flashcards');
    Route::view('revision', 'coming-soon', ['title' => __('Revision'), 'phase' => 5, 'description' => __('Everything that is due today: cards, weak concepts and past mistakes.')])->name('revision');
    Route::view('skills', 'coming-soon', ['title' => __('Skills'), 'phase' => 5, 'description' => __('Sixteen PHP skill domains tracked by evidence, not reading.')])->name('skills');
    Route::view('interview', 'coming-soon', ['title' => __('Interview'), 'phase' => 9, 'description' => __('PHP interview questions answered from understanding.')])->name('interview');
    Route::view('projects', 'coming-soon', ['title' => __('Projects'), 'phase' => 9, 'description' => __('Build real applications that reuse what you have learned.')])->name('projects');
    Route::view('tutor', 'coming-soon', ['title' => __('AI Tutor'), 'phase' => 6, 'description' => __('A tutor that knows your progress, hints before it answers, and never does the work for you.')])->name('tutor');
    Route::view('search', 'coming-soon', ['title' => __('Search'), 'phase' => 5, 'description' => __('Search lessons, concepts, examples, exercises, errors and cards.')])->name('search');

    Route::get('lessons', function (): RedirectResponse {
        $progress = LessonProgress::query()
            ->where('user_id', auth()->id())
            ->whereHas('lesson', fn ($query) => $query->published())
            ->with('lesson:id,slug')
            ->orderByDesc('last_at')
            ->first();

        // whereHas() guarantees the relation exists when a row was found.
        $slug = $progress?->lesson->slug
            ?? Lesson::query()->published()->orderBy('ord')->value('slug');

        abort_unless($slug !== null, 404);

        return redirect()->route('lessons.show', $slug);
    })->name('lessons');

    Route::livewire('lessons/{lesson:slug}', LessonView::class)->name('lessons.show');
    Route::livewire('lessons/{lesson:slug}/practice', PracticeRunner::class)->name('practice.show');
    Route::livewire('lessons/{lesson:slug}/quiz', QuizRunner::class)->name('quiz.show');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::view('/', 'admin.dashboard', ['title' => __('Admin')])->name('dashboard');
        Route::livewire('books', Index::class)->name('books');
    });
});

require __DIR__.'/settings.php';
