<div class="mx-auto grid w-full max-w-[110rem] gap-6 px-4 pt-6 pb-24 xl:grid-cols-[17rem_minmax(0,1fr)_20rem] lg:pb-6">

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
                <span class="ms-auto text-zinc-500 dark:text-zinc-400">{{ $lesson['est_minutes'] }} min</span>
            </div>

            <h1 class="mt-3 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $lesson['title'] }}</h1>

            @if (! empty($lesson['summary']))
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $lesson['summary'] }}</p>
            @endif

            <nav class="mt-4 flex gap-1 overflow-x-auto pb-1" aria-label="Lesson modes">
                @foreach (['read' => 'Read', 'teach' => 'Teach', 'practice' => 'Practice', 'quiz' => 'Quiz', 'debug' => 'Debug'] as $modeKey => $modeLabel)
                    <button
                        type="button"
                        wire:click="setMode('{{ $modeKey }}')"
                        aria-pressed="{{ $mode === $modeKey ? 'true' : 'false' }}"
                        @class([
                            'shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-zinc-500 focus-visible:ring-offset-1 focus-visible:outline-none',
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
                                <svg class="size-3.5 text-zinc-500 dark:text-zinc-400 transition group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
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
        @elseif ($mode === 'teach')
            <div class="my-5">
                <livewire:learn.tutor-chat :lesson="$lessonModel" />
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
                <h2 class="text-lg font-semibold capitalize">{{ $mode }} mode</h2>
                <p class="mt-2 text-sm text-zinc-500">
                    Arrives in Phase {{ $modePhase ?? '?' }} - the lesson context above is already wired up.
                </p>
            </div>
        @endif

        @include('partials.laravel-bridge', ['bridgeRows' => $bridgeRows])

        <nav class="mt-10 flex items-stretch justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            @if ($prev)
                <a href="{{ route('lessons.show', $prev['slug']) }}" wire:navigate class="group flex-1 rounded-xl border border-zinc-200 p-3 text-sm hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">Previous</span>
                    <span class="group-hover:underline">{{ $prev['title'] }}</span>
                </a>
            @else
                <span class="flex-1"></span>
            @endif
            @if ($next)
                <a href="{{ route('lessons.show', $next['slug']) }}" wire:navigate class="group flex-1 rounded-xl border border-zinc-200 p-3 text-right text-sm hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">Next</span>
                    <span class="group-hover:underline">{{ $next['title'] }}</span>
                </a>
            @endif
        </nav>
    </main>

    {{-- Right: context rail (tablet stacks it below; mobile uses the bottom sheet) --}}
    <aside class="hidden lg:block xl:sticky xl:top-6 xl:self-start">
        @include('livewire.learn.partials.rail-tabs', ['variant' => 'desktop'])
        @include('livewire.learn.partials.context-panel')
    </aside>

    {{-- Mobile: fixed tab bar + bottom sheet (docs/08: rail = bottom sheet <1024px) --}}
    <div class="lg:hidden" x-data="{ railSheet: false }" @keydown.escape.window="railSheet = false">
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 px-3 py-2 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            @include('livewire.learn.partials.rail-tabs', ['variant' => 'mobile'])
        </div>

        <div
            x-cloak
            x-show="railSheet"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="fixed inset-x-0 bottom-0 z-50 max-h-[70vh] overflow-y-auto rounded-t-2xl border-t border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Lesson context panel') }}"
        >
            <div class="sticky top-0 flex items-center justify-between border-b border-zinc-100 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-semibold capitalize text-zinc-900 dark:text-zinc-50">{{ $railTab }}</p>
                <button
                    type="button"
                    @click="railSheet = false"
                    class="rounded-lg px-2 py-1 text-zinc-500 transition hover:text-zinc-900 focus-visible:ring-2 focus-visible:ring-zinc-500 focus-visible:outline-none dark:hover:text-zinc-100"
                    aria-label="{{ __('Close panel') }}"
                >
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>

            <div class="p-4">
                @include('livewire.learn.partials.rail-tabs', ['variant' => 'mobile'])
                @include('livewire.learn.partials.context-panel')
            </div>
        </div>
    </div>
</div>
