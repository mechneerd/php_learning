@props(['title', 'phase', 'description' => null])

<div class="mx-auto max-w-2xl py-16 text-center">
    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
        {{ __('Phase :phase', ['phase' => $phase]) }}
    </div>

    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $title }}</h1>

    @if ($description)
        <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif

    <p class="mt-6 text-sm text-zinc-500 dark:text-zinc-500">
        {{ __('This screen is scheduled in a later phase of the implementation plan.') }}
    </p>

    <a href="{{ route('dashboard') }}" class="mt-8 inline-block text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400" wire:navigate>
        {{ __('Back to dashboard') }}
    </a>
</div>
