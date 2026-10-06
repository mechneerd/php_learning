<div class="mx-auto w-full max-w-[64rem] space-y-6 px-4 py-6">
    <header class="border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Search</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Lessons, concepts, examples, exercises and flashcards in one place.
        </p>
    </header>

    <input type="search" wire:model.live.debounce.300ms="q" autofocus
        placeholder="Search the book…  (e.g. composition, SPL, autoload)"
        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 placeholder-zinc-400 outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-100" />

    @if ($results === null)
        <x-empty-state title="Type to search"
            hint="Results are grouped by kind with snippets highlighted. The index is rebuilt with search:reindex." />
    @else
        @php($totalHits = array_sum(array_map('count', $results)))

        @if ($totalHits === 0)
            <x-empty-state title="No results for “{{ $term }}”"
                hint="Try a single word from a lesson title, concept name or code example." />
        @else
            @foreach ($results as $type => $rows)
                @if ($rows === [])
                    @continue
                @endif
                <section>
                    <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                        {{ $groups[$type] ?? ucfirst($type) }}
                        <span class="ml-1 font-normal text-zinc-500 dark:text-zinc-400">({{ count($rows) }})</span>
                    </h2>
                    <ul class="space-y-2">
                        @foreach ($rows as $hit)
                            <li class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($hit['url'] !== null)
                                            <a href="{{ $hit['url'] }}" class="text-sm font-medium text-zinc-800 hover:underline dark:text-zinc-200">{{ $hit['title'] }}</a>
                                        @else
                                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $hit['title'] }}</span>
                                        @endif
                                        <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{!! $hit['snippet'] !!}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        {{ $groups[$type] ?? $type }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    @endif
</div>
