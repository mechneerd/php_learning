<div class="mx-auto w-full max-w-[110rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Skills</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Sixteen PHP skill domains tracked by evidence, not reading.
            </p>
        </div>
        <p class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
            Reading does not raise skill levels.
        </p>
    </header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($rows as $row)
            @php($isOpen = $expanded === $row['domain']->value)
            <section @class([
                'rounded-xl border bg-white dark:bg-zinc-900',
                'border-zinc-900 dark:border-zinc-100' => $isOpen,
                'border-zinc-200 dark:border-zinc-700' => ! $isOpen,
            ])>
                <button type="button" wire:click="toggle('{{ $row['domain']->value }}')"
                    class="flex w-full items-start justify-between gap-2 p-4 text-left">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $row['domain']->label() }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $row['mastered_count'] }}/{{ $row['concept_count'] }} mastered
                            @if ($row['pass_rate'] !== null)
                                · {{ (int) round($row['pass_rate'] * 100) }}% pass
                            @endif
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[11px] font-medium uppercase',
                        match ($row['level']->value) {
                            'mastered' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
                            'comfortable' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200',
                            'practicing' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                            'learning' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
                            default => 'bg-zinc-50 text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400',
                        },
                    ])>{{ $row['level']->label() }}</span>
                </button>

                @if ($isOpen)
                    <div class="border-t border-zinc-100 p-4 dark:border-zinc-800">
                        @if ($row['concepts'] === [])
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">No concepts mapped to this domain yet.</p>
                        @else
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($row['concepts'] as $concept)
                                    <li>
                                        <x-concept-chip :name="$concept['name']" :level="$concept['level']" :slug="$concept['slug']"
                                            :detail="$concept['evidence'] === [] ? null : collect($concept['evidence'])->map(fn ($count, $key) => $key.'×'.$count)->implode(' ')" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
