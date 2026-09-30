@props(['hints' => [], 'solution' => null, 'showSolution' => false, 'solutionUnlocked' => false, 'attempts' => 0])

<section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" aria-label="Hint ladder">
    <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
        <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Hint ladder</h2>
        <p class="mt-0.5 text-xs text-zinc-400">Concept &rarr; syntax &rarr; implementation &rarr; solution</p>
    </div>

    <ol class="divide-y divide-zinc-100 dark:divide-zinc-800">
        @foreach ($hints as $hint)
            <li class="space-y-2 px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        Hint {{ $hint['level'] }}
                    </span>
                    @if (! $hint['revealed'] && $hint['unlocked'])
                        <button
                            type="button"
                            wire:click="revealHint({{ $hint['level'] }})"
                            class="rounded-lg border border-zinc-200 px-3 py-1 text-xs font-medium text-zinc-600 transition hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            Reveal
                        </button>
                    @elseif (! $hint['revealed'])
                        <span class="text-[0.7rem] text-zinc-400">
                            @if ($hint['level'] === 3)
                                after {{ \App\Services\Learning\HintLadder::HINT3_AFTER_ATTEMPTS }} attempts
                            @else
                                view the previous hint first
                            @endif
                        </span>
                    @endif
                </div>
                @if ($hint['revealed'])
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $hint['text'] }}</p>
                @endif
            </li>
        @endforeach

        {{-- Solution --}}
        <li class="space-y-2 px-4 py-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Solution</span>
                @if (! $showSolution && $solutionUnlocked && $solution)
                    <button
                        type="button"
                        wire:click="revealSolution"
                        class="rounded-lg border border-amber-300 px-3 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950/40"
                    >
                        Reveal
                    </button>
                @elseif (! $showSolution)
                    <span class="text-[0.7rem] text-zinc-400">after all hints + {{ \App\Services\Learning\HintLadder::SOLUTION_AFTER_SECONDS }}s, or {{ \App\Services\Learning\HintLadder::SOLUTION_AFTER_FAILURES }} failed attempts</span>
                @endif
            </div>
            @if ($showSolution && $solution)
                <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs leading-relaxed text-zinc-100">{{ $solution }}</pre>
            @endif
        </li>
    </ol>

    <p class="border-t border-zinc-100 px-4 py-2 text-[0.7rem] text-zinc-400 dark:border-zinc-800">
        {{ $attempts }} attempt(s) so far &middot; hints used count against mastery weight
    </p>
</section>
