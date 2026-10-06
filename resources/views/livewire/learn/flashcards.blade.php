<div class="mx-auto w-full max-w-[44rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Flashcards</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Tap or press space to flip &middot; grade yourself to schedule the next review.
            </p>
        </div>
        @if (! $done)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $reviewed }} reviewed &middot; {{ $remaining }} left in this session</p>
        @endif
    </header>

    @if ($done)
        <section class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
            @if ($reviewed > 0)
                <p class="text-lg font-medium text-zinc-800 dark:text-zinc-200">Session complete</p>
                <p class="mt-1 text-sm text-zinc-500">{{ $reviewed }} card(s) reviewed. Cards come back on schedule.</p>
                <button
                    type="button"
                    wire:click="$refresh"
                    class="mt-4 rounded-lg border border-zinc-200 px-4 py-1.5 text-sm font-medium text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300"
                >
                    Start another session
                </button>
            @else
                <p class="text-lg font-medium text-zinc-800 dark:text-zinc-200">Nothing due right now</p>
                <p class="mt-1 text-sm text-zinc-500">Seed cards with <code>php artisan db:seed --class=FlashcardSeeder</code>, then check back later.</p>
            @endif
        </section>
    @else
        <x-flashcard :card="$card" :flipped="$flipped" :next-intervals="$nextIntervals" />
    @endif
</div>
