<div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
    <header class="border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div class="flex flex-wrap items-center gap-2">
            @if ($pattern !== null)
                <a href="{{ route('errors') }}" wire:navigate class="text-xs text-zinc-500 hover:underline">&larr; Error library</a>
                <span @class(['rounded-full px-2 py-0.5 text-[11px] font-medium', $pattern->category->badgeClass()])>
                    {{ $pattern->category->label() }}
                </span>
            @endif
        </div>
        <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
            {{ $pattern !== null ? $pattern->name : 'Error Library' }}
        </h1>
        @if ($pattern === null)
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Repeatable failures and how to beat them: what happened, why, identify, fix, prevent, practice.
            </p>
        @endif
    </header>

    @if ($pattern !== null)
        <div class="space-y-4">
            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">1 · What happened</p>
                <p class="mt-2 font-mono text-sm leading-6 break-words text-red-700 dark:text-red-400">{{ $pattern->symptom }}</p>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">2 · Why</p>
                <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $pattern->cause }}</p>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">3 · How to identify it</p>
                <ol class="mt-2 list-decimal space-y-1.5 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                    @foreach ($pattern->identify_steps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">4 · How to fix it</p>
                <ol class="mt-2 list-decimal space-y-1.5 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                    @foreach ($pattern->fix_steps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">5 · How to prevent it</p>
                <ol class="mt-2 list-decimal space-y-1.5 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                    @foreach ($pattern->prevent_steps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">6 · Practice</p>
                @if ($pattern->practice_ref !== null)
                    <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $pattern->practice_ref }}</p>
                @else
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">No practice drill linked yet.</p>
                @endif
            </section>
        </div>
    @elseif ($grouped === [])
        <x-empty-state title="No error patterns yet"
            hint="Patterns are published by the content pipeline — check back soon." />
    @else
        <div class="space-y-6">
            @foreach ($grouped as $group)
                <section>
                    <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                        {{ $group['label'] }}
                    </h2>
                    <ul class="space-y-2">
                        @foreach ($group['patterns'] as $entry)
                            <li>
                                <a href="{{ route('errors.show', $entry->slug) }}" wire:navigate
                                    class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->name }}</span>
                                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->symptom }}</span>
                                    </span>
                                    <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', $group['badge']])>
                                        {{ $group['label'] }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>
