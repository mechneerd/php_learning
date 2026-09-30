<div class="mx-auto grid w-full max-w-[110rem] gap-6 px-4 py-6 xl:grid-cols-[17rem_minmax(0,1fr)_20rem]">

    {{-- Left: course navigation (desktop) --}}
    <aside class="hidden xl:block">
        <div class="sticky top-6 max-h-[calc(100vh-5rem)] overflow-y-auto pe-1">
            <p class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Contents</p>
            @include('livewire.learn.partials.nav-tree', ['tree' => $tree, 'currentId' => $lesson['id']])
        </div>
    </aside>

    {{-- Center: lesson --}}
    <main class="min-w-0">
        <details class="mb-4 rounded-xl border border-zinc-200 bg-zinc-50 xl:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <summary class="cursor-pointer px-4 py-2 text-sm font-medium">Course contents</summary>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                @include('livewire.learn.partials.nav-tree', ['tree' => $tree, 'currentId' => $lesson['id']])
            </div>
        </details>

        <header class="mb-6 border-b border-zinc-200 pb-4 dark:border-zinc-700">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-source-badge :source="$lesson['source']" :label="$lesson['source_label']" />
                <x-citation-line :citation="$lesson['citation']" />
                @if ($lesson['is_outdated'])
                    <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-700 dark:bg-red-900/40 dark:text-red-200">Outdated</span>
                @endif
                <span class="ms-auto text-zinc-400">{{ $lesson['est_minutes'] }} min</span>
            </div>

            <h1 class="mt-3 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $lesson['title'] }}</h1>

            @if (! empty($lesson['summary']))
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $lesson['summary'] }}</p>
            @endif

            <nav class="mt-4 flex flex-wrap gap-1" aria-label="Lesson modes">
                @foreach (['read' => 'Read', 'teach' => 'Teach', 'practice' => 'Practice', 'quiz' => 'Quiz', 'debug' => 'Debug'] as $modeKey => $modeLabel)
                    <button
                        type="button"
                        wire:click="setMode('{{ $modeKey }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $mode === $modeKey,
                            'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200' => $mode !== $modeKey,
                        ])
                    >
                        {{ $modeLabel }}
                    </button>
                @endforeach
            </nav>
        </header>

        @if ($mode === 'read')
            @forelse ($blocks as $block)
                @php($blockView = view()->exists('lessons.blocks.'.$block['type']) ? 'lessons.blocks.'.$block['type'] : 'lessons.blocks.fallback')
                @if ($block['collapsed'])
                    <details class="group my-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium text-zinc-600 select-none hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-900">
                            <span class="flex items-center gap-2">
                                <svg class="size-3.5 text-zinc-400 transition group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                                {{ $block['type_label'] }}
                            </span>
                            <x-citation-line :citation="$block['citation']" class="text-xs" />
                        </summary>
                        <div class="border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                            @include($blockView, ['block' => $block, 'codeExamples' => $codeExamples, 'diagrams' => $diagrams])
                        </div>
                    </details>
                @else
                    <div class="my-5">
                        @include($blockView, ['block' => $block, 'codeExamples' => $codeExamples, 'diagrams' => $diagrams])
                    </div>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    This lesson has no content blocks yet.
                </div>
            @endforelse
        @else
            <div class="rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
                <h2 class="text-lg font-semibold capitalize">{{ $mode }} mode</h2>
                <p class="mt-2 text-sm text-zinc-500">
                    Arrives in Phase {{ $modePhase ?? '?' }} — the lesson context above is already wired up.
                </p>
            </div>
        @endif

        <nav class="mt-10 flex items-stretch justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            @if ($prev)
                <a href="{{ route('lessons.show', $prev['slug']) }}" wire:navigate class="group flex-1 rounded-xl border border-zinc-200 p-3 text-sm hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <span class="block text-xs text-zinc-400">Previous</span>
                    <span class="group-hover:underline">{{ $prev['title'] }}</span>
                </a>
            @else
                <span class="flex-1"></span>
            @endif
            @if ($next)
                <a href="{{ route('lessons.show', $next['slug']) }}" wire:navigate class="group flex-1 rounded-xl border border-zinc-200 p-3 text-right text-sm hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <span class="block text-xs text-zinc-400">Next</span>
                    <span class="group-hover:underline">{{ $next['title'] }}</span>
                </a>
            @endif
        </nav>
    </main>

    {{-- Right: context rail --}}
    <aside class="xl:sticky xl:top-6 xl:self-start">
        <div class="flex gap-1 rounded-xl bg-zinc-100 p-1 text-sm dark:bg-zinc-900" role="tablist">
            @foreach (['progress' => 'Progress', 'concepts' => 'Concepts', 'notes' => 'Notes', 'tutor' => 'Tutor'] as $tabKey => $tabLabel)
                <button
                    type="button"
                    role="tab"
                    wire:click="setRailTab('{{ $tabKey }}')"
                    @class([
                        'flex-1 rounded-lg px-2 py-1.5 font-medium transition',
                        'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-zinc-50' => $railTab === $tabKey,
                        'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' => $railTab !== $tabKey,
                    ])
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <div class="mt-3 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            @if ($railTab === 'progress')
                @include('livewire.learn.partials.progress-panel', ['lesson' => $lesson, 'progress' => $progress])
            @elseif ($railTab === 'concepts')
                <p class="text-zinc-500">Concepts, prerequisites and mastery chips arrive in <strong>Phase 3</strong>.</p>
            @elseif ($railTab === 'notes')
                <p class="text-zinc-500">Notes, highlights and bookmarks arrive in <strong>Phase 5</strong>.</p>
            @else
                <p class="text-zinc-500">The lesson-anchored AI tutor arrives in <strong>Phase 6</strong>. Reading a lesson never calls the AI.</p>
            @endif
        </div>
    </aside>
</div>
