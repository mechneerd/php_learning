<div class="mx-auto w-full max-w-[110rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Projects</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Guided builds that reuse what you have learned. A project unlocks when its concepts reach
                <span class="font-medium">Comfortable</span>.
            </p>
        </div>
    </header>

    @if ($levels === [])
        <x-empty-state title="No projects published yet"
            hint="Projects arrive with the content pipeline — check back soon." />
    @else
        @foreach ($levels as $group)
            <section>
                <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                    {{ $group['level']->label() }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($group['projects'] as $entry)
                        @php($cardProject = $entry['model'])
                        <a href="{{ route('projects.show', $cardProject->slug) }}" wire:navigate
                            @class([
                                'group flex flex-col rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500',
                                'opacity-70' => ! $entry['unlocked'],
                            ])>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                                    {{ $cardProject->title }}
                                </h3>
                                <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', $group['level']->badgeClass()])>
                                    {{ $group['level']->label() }}
                                </span>
                            </div>
                            <p class="mt-2 line-clamp-3 flex-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                {{ $cardProject->brief }}
                            </p>
                            <div class="mt-3 flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                                @if ($entry['unlocked'])
                                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <flux:icon.check-circle class="size-3.5" />
                                        Unlocked
                                    </span>
                                    <span>{{ $entry['checked'] }}/{{ $entry['total'] }} tasks</span>
                                @else
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.lock-closed class="size-3.5" />
                                        Locked &mdash; concepts not comfortable yet
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
