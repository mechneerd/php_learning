<div class="mx-auto w-full max-w-[110rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Revision</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Everything that is due: cards, weak concepts and past mistakes.
            </p>
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $total }} item{{ $total === 1 ? '' : 's' }} due</p>
    </header>

    @if ($total === 0)
        <x-empty-state title="Nothing due"
            hint="{{ $nextDueAt !== null ? 'Your next review is '.$nextDueAt->diffForHumans().'.' : 'Grade some flashcards or submit an attempt and the queue fills itself.' }}">
            <a href="{{ route('flashcards') }}"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-50 hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                Review cards
            </a>
        </x-empty-state>
    @else
        @foreach ($groups as $group)
            <section>
                <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $group['label'] }}</h2>
                <ul class="space-y-2">
                    @foreach ($group['items'] as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $entry['title'] }}</p>
                                @if ($entry['detail'] !== '')
                                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $entry['detail'] }}</p>
                                @endif
                                <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">reason: {{ $entry['reason'] }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($entry['url'] !== null)
                                    <a href="{{ $entry['url'] }}"
                                        class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Open</a>
                                @endif
                                <button type="button" wire:click="snooze({{ $entry['id'] }})"
                                    class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">
                                    Snooze 1d
                                </button>
                                <button type="button" wire:click="markDone({{ $entry['id'] }})"
                                    class="rounded-lg bg-zinc-900 px-2.5 py-1 text-xs font-medium text-zinc-50 hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                                    Mark done
                                </button>
                            </div>
                            @if ($entry['concept'] ?? null)
                                <div class="w-full" wire:key="recall-{{ $entry['id'] }}">
                                    <livewire:learn.recall-prompt :concept="$entry['concept']" />
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
