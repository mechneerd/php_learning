<x-layouts::app.sidebar :title="__('Admin')">
    <flux:main class="mx-auto max-w-5xl p-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Admin') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Import the book, manage content and approve AI-generated lessons.') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Books & Import') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Upload the PDF, extract pages, detect chapters and sections.') }}
                </flux:text>
                <flux:badge class="mt-3" color="amber">{{ __('Phase 1') }}</flux:badge>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Lessons & Blocks') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Edit lesson content blocks with source and page citations.') }}
                </flux:text>
                <flux:badge class="mt-3" color="amber">{{ __('Phase 2') }}</flux:badge>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Review Queue') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Approve, edit or reject AI-generated content before it is published.') }}
                </flux:text>
                <flux:badge class="mt-3" color="amber">{{ __('Phase 7') }}</flux:badge>
            </flux:card>
        </div>
    </flux:main>
</x-layouts.app.sidebar>
