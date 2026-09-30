<?php

use App\Jobs\ExtractPdfJob;
use App\Livewire\Admin\Books\Index;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('queues a pdf import for admins', function () {
    Storage::fake('local');
    Queue::fake();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->set('title', 'PHP 8 Objects, Patterns, and Practice')
        ->set('pdf', UploadedFile::fake()->create('book.pdf', 512, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    Queue::assertPushed(ExtractPdfJob::class, fn ($job) => str_contains($job->originalName, 'book.pdf'));

    expect(Book::where('title', 'PHP 8 Objects, Patterns, and Practice')->exists())->toBeTrue();
});

it('validates the upload', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title', 'pdf']);
});

it('refuses learners', function () {
    $learner = User::factory()->create();

    expect($learner->isAdmin())->toBeFalse()
        ->and($learner->can('create', Book::class))->toBeFalse()
        ->and($learner->can('update', Book::factory()->create()))->toBeFalse();

    $this->actingAs($learner)->get(route('admin.books'))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('admin.books'))->assertRedirect(route('login'));
});
