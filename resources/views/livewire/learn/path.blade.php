<div class="mx-auto w-full max-w-[110rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Learning Path</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $conceptCount }} concepts, prerequisite edges from the knowledge map, gates per stage.
            </p>
        </div>
        <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
            @foreach ($levels as $level)
                <li class="flex items-center gap-1.5">
                    <span class="size-3 rounded-full border border-zinc-400" style="background-color: {{ $level->color() }}"></span>
                    {{ $level->label() }}
                </li>
            @endforeach
        </ul>
    </header>

    {{-- Horizontal stage rail --}}
    <section aria-label="Stages">
        <p class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Stages</p>
        <ol class="flex gap-2 overflow-x-auto pb-2">
            @foreach ($rail as $entry)
                @php($stage = $entry['stage'])
                <li @class([
                    'min-w-40 flex-1 rounded-xl border p-3',
                    'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30' => $entry['locked'],
                    'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => ! $entry['locked'],
                ])>
                    <div class="flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>Stage {{ $stage->number }}</span>
                        @if ($entry['locked'])
                            <span class="rounded-full bg-amber-200 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">Gated</span>
                        @elseif ($entry['gate']->isGated() && $entry['gate']->passed())
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">Open</span>
                        @endif
                    </div>
                    <p class="mt-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $stage->name }}</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-zinc-800 dark:bg-zinc-200" style="width: {{ $entry['pct'] ?? 0 }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $entry['pct'] === null ? 'No lessons yet' : $entry['pct'].'% read' }}
                    </p>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Concept graph --}}
        <section class="min-w-0 rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Concept graph</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-mono">A --&gt; B</span> hard prerequisite ·
                    <span class="font-mono">A -.-&gt; B</span> used later ·
                    dashed ring = recommended
                </p>
            </div>
            @if ($mermaid !== '')
                <div class="mermaid overflow-x-auto p-4 text-center">{{ $mermaid }}</div>
            @else
                <div class="p-8 text-center text-sm text-zinc-500">
                    No prerequisite edges yet. Seed the concept graph to see it here.
                </div>
            @endif
        </section>

        {{-- Right rail --}}
        <aside class="space-y-4">
            {{-- Next recommendation --}}
            <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <p class="border-b border-zinc-100 px-4 pt-3 text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-800">
                    Next up
                </p>
                @if ($recommendation)
                    <div class="p-4">
                        <p class="font-medium text-zinc-900 dark:text-zinc-50">{{ $recommendation->title }}</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Stage {{ $recommendation->stage?->number }} · {{ $recommendation->est_minutes }} min
                        </p>
                        <a href="{{ route('lessons.show', $recommendation->slug) }}" wire:navigate
                           class="mt-3 inline-flex rounded-lg bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">
                            Start reading
                        </a>
                    </div>
                @else
                    <p class="p-4 text-sm text-zinc-500">Every published lesson has been read. Practice comes in Phase 4.</p>
                @endif
            </section>

            {{-- Gate panel --}}
            <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <p class="border-b border-zinc-100 px-4 pt-3 text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-800">
                    Stage gate
                </p>
                @if ($nextGate)
                    <div class="p-4">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-50">
                            Enter Stage {{ $nextGate['stage']->number }} — {{ $nextGate['stage']->name }}
                        </p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($nextGate['result']->checks as $check)
                                <li class="flex items-start justify-between gap-3 text-sm">
                                    <span class="flex items-center gap-2">
                                        @if ($check->passed)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">pass</span>
                                        @else
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-300">fail</span>
                                        @endif
                                        <span class="text-zinc-700 dark:text-zinc-300">{{ $check->label }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $check->have }} / {{ $check->need }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $nextGate['result']->passedCount() }} of {{ count($nextGate['result']->checks) }} requirements met.
                        </p>
                    </div>
                @elseif ($gatedStages > 0)
                    <p class="p-4 text-sm text-zinc-500">All stage gates are open.</p>
                @else
                    <p class="p-4 text-sm text-zinc-500">No stage gates defined yet.</p>
                @endif
            </section>

            {{-- Selected concept --}}
            @if ($concept)
                <section class="rounded-xl border border-sky-200 bg-sky-50/50 dark:border-sky-800 dark:bg-sky-950/30">
                    <div class="flex items-start justify-between gap-2 px-4 pt-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-300">Concept</p>
                            <h2 class="mt-0.5 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ $concept->name }}</h2>
                        </div>
                        <button type="button" wire:click="$set('selected', null)"
                                class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">Clear</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 px-4 pt-2 text-xs">
                        <span class="rounded-full bg-white px-2 py-0.5 font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">{{ $concept->skill_domain }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5 font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">{{ $concept->granularity->label() }}</span>
                        @if ($concept->is_core)
                            <span class="rounded-full bg-white px-2 py-0.5 font-medium text-sky-700 ring-1 ring-sky-200 dark:bg-zinc-900 dark:text-sky-300 dark:ring-sky-800">gates</span>
                        @endif
                        <span class="rounded-full bg-white px-2 py-0.5 font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">{{ $concept->source->label() }}</span>
                    </div>
                    <p class="px-4 py-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $concept->definition }}</p>

                    @if ($concept->prerequisites->isNotEmpty())
                        <div class="border-t border-sky-100 px-4 py-3 dark:border-sky-900/50">
                            <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Learn this first</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($concept->prerequisites as $prereq)
                                    <button type="button" wire:click="select('{{ $prereq->slug }}')"
                                            class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200 hover:ring-zinc-400 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">
                                        {{ $prereq->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($concept->dependents->isNotEmpty())
                        <div class="border-t border-sky-100 px-4 py-3 dark:border-sky-900/50">
                            <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Used later in</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($concept->dependents as $dependent)
                                    <button type="button" wire:click="select('{{ $dependent->slug }}')"
                                            class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200 hover:ring-zinc-400 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">
                                        {{ $dependent->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php($linkedLessons = $concept->lessons->filter(fn ($lesson) => $lesson->status->isPublished()))
                    @if ($linkedLessons->isNotEmpty())
                        <div class="border-t border-sky-100 px-4 py-3 dark:border-sky-900/50">
                            <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Lessons</p>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($linkedLessons as $lesson)
                                    <li>
                                        <a href="{{ route('lessons.show', $lesson->slug) }}" wire:navigate
                                           class="text-sky-700 hover:underline dark:text-sky-300">{{ $lesson->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @else
                <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="border-b border-zinc-100 px-4 pt-3 text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-800">
                        Core concepts
                    </p>
                    <div class="flex flex-wrap gap-1.5 p-4">
                        @forelse ($coreConcepts as $core)
                            <button type="button" wire:click="select('{{ $core->slug }}')"
                                    class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                {{ $core->name }}
                            </button>
                        @empty
                            <p class="text-sm text-zinc-500">No concepts seeded yet.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
