<x-recall-card title="Explain it" hint="Type your own explanation - scoring lists what you missed.">
    <form wire:submit="score" class="space-y-3">
        <textarea wire:model="answer" rows="4"
            placeholder="Explain {{ $concept->name }} in your own words..."
            class="w-full resize-none rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100 dark:focus:border-zinc-100"></textarea>

        @error('answer')
            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        <div class="flex items-center justify-between gap-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="score"
                class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-50 hover:bg-zinc-700 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                Score my explanation
            </button>
            @if ($evaluation !== null)
                <button type="button" wire:click="retry"
                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs text-zinc-500 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-400">
                    Try again
                </button>
            @endif
        </div>
    </form>

    @if ($notice !== null)
        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400" role="status">{{ $notice }}</p>
    @endif

    @if ($evaluation !== null)
        <div class="mt-3 space-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ (int) round($evaluation['score']) }}%</span>
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <span class="block h-full rounded-full {{ $evaluation['score'] >= 70 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                        style="width: {{ (int) round($evaluation['score']) }}%"></span>
                </span>
            </div>

            @if ($evaluation['matched_points'] !== [])
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">You covered</p>
                    <ul class="mt-1 space-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                        @foreach ($evaluation['matched_points'] as $point)
                            <li class="flex gap-1.5"><span class="text-emerald-500">+</span> <span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($evaluation['missing_points'] !== [])
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">Missing points</p>
                    <ul class="mt-1 space-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                        @foreach ($evaluation['missing_points'] as $point)
                            <li class="flex gap-1.5"><span class="text-amber-500">-</span> <span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</x-recall-card>
