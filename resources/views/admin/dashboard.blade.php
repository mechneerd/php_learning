<x-layouts::app.sidebar :title="__('Admin')">
    <flux:main class="mx-auto max-w-5xl p-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Admin') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Import the book, generate content, and approve AI drafts before they reach learners.') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Books & Import') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Upload the PDF, extract pages, detect chapters and sections.') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" :href="route('admin.books')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Review Queue') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Diff, approve or reject AI-generated content before it is published.') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" variant="primary" :href="route('admin.review')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Lessons & Blocks') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Edit lesson content blocks with source and page citations.') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" :href="route('admin.lessons')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Concept Graph') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Concepts, definitions and prerequisite edges (acyclic).') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" :href="route('admin.concepts')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Import Jobs') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Generation ledger, token budget, queue depth and retries.') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" :href="route('admin.import-jobs')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Analytics') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Coverage per stage, exercise pass rates and the AI spend chart.') }}
                </flux:text>
                <flux:button class="mt-3" size="sm" :href="route('admin.analytics')" wire:navigate>{{ __('Open') }}</flux:button>
            </flux:card>

            <flux:card class="p-5">
                <flux:heading size="sm">{{ __('Shell commands') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('php artisan content:generate {stage} --dry-run  ·  content:approve lesson {id}') }}
                </flux:text>
                <flux:badge class="mt-3" color="amber">{{ __('Phase 7') }}</flux:badge>
            </flux:card>
        </div>
    </flux:main>
</x-layouts.app.sidebar>
