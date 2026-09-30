<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading level="2">{{ __('Books') }}</flux:heading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Upload the source PDF. Text is extracted page by page, then chapters and sections are derived from the table of contents.') }}
            </p>
        </div>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" class="mb-0">
            {{ $notice }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
        <flux:input wire:model="title" :label="__('Title')" />

        <flux:input wire:model="pdf" type="file" :label="__('Source PDF')" accept="application/pdf" />

        @error('pdf') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        @error('title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

        <div>
            <flux:button variant="primary" type="submit">{{ __('Import book') }}</flux:button>
        </div>
    </form>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('PDF documents') }}</flux:table.column>
            <flux:table.column>{{ __('Chapters') }}</flux:table.column>
            <flux:table.column>{{ __('Lessons') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($books as $book)
                <flux:table.row :key="$book->id">
                    <flux:table.cell>{{ $book->title }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$book->status->value === 'active' ? 'green' : 'gray'">
                            {{ $book->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $book->pdf_documents_count }}</flux:table.cell>
                    <flux:table.cell>{{ $book->chapters_count }}</flux:table.cell>
                    <flux:table.cell>{{ $book->chapters->sum('lessons_count') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No books imported yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
