<?php

namespace App\Livewire\Admin\Books;

use App\Enums\BookStatus;
use App\Http\Requests\StoreBookRequest;
use App\Jobs\ExtractPdfJob;
use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Books')]
class Index extends Component
{
    use WithFileUploads;

    public string $title = 'PHP 8 Objects, Patterns, and Practice';

    /**
     * Temporary upload handled by Livewire's file upload support.
     *
     * @var TemporaryUploadedFile|null
     */
    public $pdf = null;

    public string $notice = '';

    public function save(): void
    {
        Gate::authorize('create', Book::class);

        $validated = $this->validate(StoreBookRequest::baseRules());

        /** @var UploadedFile $pdf */
        $pdf = $validated['pdf'];

        $relative = $pdf->store('uploads');

        if (! is_string($relative)) {
            abort(500, 'The PDF could not be stored.');
        }

        $book = Book::firstOrCreate(
            ['title' => $validated['title']],
            ['status' => BookStatus::Active],
        );

        ExtractPdfJob::dispatch(
            $book->id,
            Storage::disk('local')->path($relative),
            $pdf->getClientOriginalName(),
        );

        $this->notice = __('Import queued. Pages and chapters appear when the job finishes.');

        $this->reset('pdf', 'title');
    }

    /**
     * @return View
     */
    public function render()
    {
        return view('livewire.admin.books.index', [
            'books' => Book::query()
                ->withCount(['chapters', 'pdfDocuments'])
                ->with(['chapters' => fn ($query) => $query->withCount('lessons')])
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
