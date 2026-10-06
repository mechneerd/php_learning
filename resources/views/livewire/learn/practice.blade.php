<div class="mx-auto w-full max-w-[80rem] space-y-6 px-4 py-6">
    {{-- Header --}}
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Practice</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($lesson)
                    <a href="{{ route('lessons.show', $lesson->slug) }}" class="underline hover:text-zinc-700 dark:hover:text-zinc-200">
                        {{ $lesson->title }}
                    </a>
                    &middot;
                @endif
                {{ $exercises->count() }} exercise(s) &middot; {{ $attemptsCount }} attempt(s) on this one
            </p>
        </div>
        <p class="rounded-lg bg-zinc-100 px-3 py-1 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
            Sandboxed runs &middot; {{ (int) config('runner.limits.wall_seconds') }}s limit &middot; {{ (int) config('runner.limits.memory_mb') }} MB
        </p>
    </header>

    {{-- Exercise chips --}}
    @if ($exercises->count() > 1)
        <nav aria-label="Exercises">
            <ul class="flex flex-wrap gap-2">
                @foreach ($exercises as $chip)
                    <li>
                        <button
                            type="button"
                            wire:click="select({{ $chip->id }})"
                            @class([
                                'rounded-full border px-3 py-1 text-xs font-medium transition',
                                'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $chip->id === $exerciseId,
                                'border-zinc-200 text-zinc-500 hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $chip->id !== $exerciseId,
                            ])
                        >
                            #{{ $chip->ord }} &middot; {{ $chip->type->label() }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @if ($exercise)
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-4">
                {{-- Prompt --}}
                <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $exercise->type->label() }}
                        </h2>
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ $exercise->difficulty->label() }}
                        </span>
                    </div>
                    <div class="prose-sm space-y-3 p-4 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                        {!! nl2br(e($exercise->prompt)) !!}
                    </div>
                </section>

                {{-- Editor --}}
                <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-100 px-4 py-2 dark:border-zinc-800">
                        <p class="font-mono text-xs tracking-wide text-zinc-500 dark:text-zinc-400">answer.php</p>
                    </div>
                    <div class="editor-host p-2">
                        <textarea
                            wire:model="answer"
                            data-php-editor
                            rows="14"
                            spellcheck="false"
                            class="sr-only font-mono text-sm"
                            aria-label="Your answer"
                        ></textarea>
                        <div wire:ignore data-php-editor-target class="min-h-64 rounded-lg bg-zinc-50 dark:bg-zinc-950"></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        @if ($exercise->type->isCode())
                            <button
                                type="button"
                                wire:click="run"
                                wire:loading.attr="disabled"
                                @disabled($runPending)
                                class="rounded-lg bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                            >
                                <span wire:loading.remove wire:target="run">Run</span>
                                <span wire:loading wire:target="run">Running&hellip;</span>
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="submit"
                            class="rounded-lg bg-zinc-900 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                        >
                            Check answer
                        </button>
                        <button
                            type="button"
                            wire:click="resetAnswer"
                            class="rounded-lg border border-zinc-200 px-4 py-1.5 text-sm font-medium text-zinc-600 transition hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-zinc-100"
                        >
                            Reset
                        </button>
                        @if (count($history) > 0)
                            <button
                                type="button"
                                wire:click="next"
                                class="rounded-lg border border-emerald-300 px-4 py-1.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            >
                                Next exercise
                            </button>
                        @endif
                    </div>
                </section>

                @if ($runNotice !== '')
                    <p role="alert" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        {{ $runNotice }}
                    </p>
                @endif

                {{-- Live run result --}}
                @if ($liveRun !== null)
                    <section
                        aria-label="Run result"
                        @if ($runPending) wire:poll.1s="syncRun" @endif
                        class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            <span class="rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset {{ $liveRun['badge'] }}">
                                {{ $liveRun['label'] }}
                            </span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                sandbox run
                                @if ($liveRun['duration_ms'] !== null)
                                    &middot; {{ $liveRun['duration_ms'] }} ms
                                @endif
                                @if ($liveRun['exit_code'] !== null)
                                    &middot; exit {{ $liveRun['exit_code'] }}
                                @endif
                            </span>
                        </div>
                        <div class="space-y-3 p-4">
                            @if ($liveRun['error'] !== null)
                                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                    {{ $liveRun['error'] }}
                                </p>
                            @endif
                            @if ($liveRun['stdout'] !== '')
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Output</p>
                                    <pre class="max-h-64 overflow-auto rounded-lg bg-zinc-950 p-3 text-xs text-zinc-100">{{ $liveRun['stdout'] }}</pre>
                                </div>
                            @endif
                            @if ($liveRun['stderr'] !== '')
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Errors</p>
                                    <pre class="max-h-64 overflow-auto rounded-lg bg-zinc-950 p-3 text-xs text-rose-300">{{ $liveRun['stderr'] }}</pre>
                                </div>
                            @endif
                            @if ($liveRun['truncated'])
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Output was truncated at {{ (int) config('runner.limits.output_bytes') }} bytes.</p>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Result --}}
                @if ($grade)
                    <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            <span class="rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset {{ $grade['badge'] }}">
                                {{ $grade['label'] }}
                            </span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ ($grade['live'] ?? false) ? 'sandbox run + static rules' : 'static rules' }}
                            </span>
                        </div>
                        <div class="space-y-3 p-4">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $grade['feedback'] }}</p>
                            @foreach ($grade['tests'] as $test)
                                <x-test-result-row :test="$test" />
                            @endforeach
                            @if ($exercise->explanation)
                                <div class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                                    <p class="mb-1 text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 uppercase">Explanation</p>
                                    {!! nl2br(e($exercise->explanation)) !!}
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Attempt history --}}
                @if ($history !== [])
                    <section class="rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900" aria-label="Recent attempts">
                        <p class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 uppercase">Recent attempts</p>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($history as $attempt)
                                <li class="flex items-center gap-2 rounded-full border border-zinc-100 px-3 py-1 text-xs dark:border-zinc-800">
                                    <span class="rounded-full px-2 py-0.5 font-medium ring-1 ring-inset {{ $attempt['badge'] }}">{{ $attempt['label'] }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">hints {{ $attempt['hints_used'] }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $attempt['duration_sec'] }}s</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $attempt['at'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{-- Hint ladder --}}
            <aside class="space-y-4">
                <x-hint-ladder
                    :hints="$hints"
                    :solution="$exercise->solution_code"
                    :show-solution="$showSolution"
                    :solution-unlocked="$solutionUnlocked"
                    :attempts="$attemptsCount"
                />
            </aside>
        </div>
    @else
        <p class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
            No exercises here yet. Seed them with <code>php artisan db:seed --class=ExerciseSeeder</code>.
        </p>
    @endif
</div>
