<?php

use App\Livewire\Admin\Analytics;
use App\Livewire\Admin\Books\Index;
use App\Livewire\Admin\ConceptGraphEditor;
use App\Livewire\Admin\ImportJobs;
use App\Livewire\Admin\LessonEditor;
use App\Livewire\Admin\ReviewQueue;
use App\Livewire\Learn\Dashboard;
use App\Livewire\Learn\ErrorLibrary;
use App\Livewire\Learn\FlashcardSession;
use App\Livewire\Learn\InterviewMode;
use App\Livewire\Learn\LessonView;
use App\Livewire\Learn\NotesList;
use App\Livewire\Learn\PathView;
use App\Livewire\Learn\PracticeRunner;
use App\Livewire\Learn\ProjectDetail;
use App\Livewire\Learn\Projects;
use App\Livewire\Learn\QuizRunner;
use App\Livewire\Learn\RevisionQueue;
use App\Livewire\Learn\SearchPage;
use App\Livewire\Learn\SkillsView;
use App\Livewire\Learn\TutorChat;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('path', PathView::class)->name('path');
    Route::livewire('concepts/{concept:slug}', PathView::class)->name('concepts.show');
    Route::view('book', 'coming-soon', ['title' => __('Book'), 'phase' => 2, 'description' => __('Browse the book by part, chapter and section with original page numbers.')])->name('book');
    Route::livewire('practice', PracticeRunner::class)->name('practice');
    Route::livewire('quiz', QuizRunner::class)->name('quiz');
    Route::view('debug', 'coming-soon', ['title' => __('Debug Lab'), 'phase' => 9, 'description' => __('Find and fix broken PHP code.')])->name('debug');
    Route::livewire('flashcards', FlashcardSession::class)->name('flashcards');
    Route::livewire('revision', RevisionQueue::class)->name('revision');
    Route::livewire('skills', SkillsView::class)->name('skills');
    Route::livewire('interview', InterviewMode::class)->name('interview');
    Route::livewire('projects', Projects::class)->name('projects');
    Route::livewire('projects/{project:slug}', ProjectDetail::class)->name('projects.show');
    Route::livewire('errors', ErrorLibrary::class)->name('errors');
    Route::livewire('errors/{pattern:slug}', ErrorLibrary::class)->name('errors.show');
    Route::livewire('tutor', TutorChat::class)->name('tutor');
    Route::livewire('search', SearchPage::class)->name('search');
    Route::livewire('notes', NotesList::class)->name('notes');

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
        Route::livewire('review', ReviewQueue::class)->name('review');
        Route::livewire('lessons', LessonEditor::class)->name('lessons');
        Route::livewire('lessons/{lessonId}', LessonEditor::class)->name('lessons.edit');
        Route::livewire('concepts', ConceptGraphEditor::class)->name('concepts');
        Route::livewire('import-jobs', ImportJobs::class)->name('import-jobs');
        Route::livewire('analytics', Analytics::class)->name('analytics');
    });
});

require __DIR__.'/settings.php';
