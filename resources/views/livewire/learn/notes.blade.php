<div class="mx-auto w-full max-w-[64rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Notes</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Notes, highlights and bookmarks, each linking back to its lesson.
            </p>
        </div>
    </header>

    @if ($groups === [])
        <x-empty-state title="No notes yet"
            hint="Open a lesson and use the Notes tab in the context rail to capture something worth remembering." />
    @else
        @foreach ($groups as $group)
            <section>
                <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $group['label'] }}</h2>
                <ul class="space-y-2">
                    @foreach ($group['items'] as $entry)
                        <li class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="min-w-0 flex-1">
                                <p class="whitespace-pre-wrap text-sm text-zinc-800 dark:text-zinc-200">{{ $entry['body'] }}</p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $entry['at']?->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($entry['url'] !== null)
                                    <a href="{{ $entry['url'] }}"
                                        class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Open lesson</a>
                                @endif
                                <button type="button" wire:click="delete({{ $entry['id'] }})"
                                    wire:confirm="Delete this note?"
                                    class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-red-400 hover:text-red-600 dark:border-zinc-700 dark:text-zinc-300">
                                    Delete
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
