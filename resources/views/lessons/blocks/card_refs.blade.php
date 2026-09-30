@php($payload = $block['payload'] ?? [])
<div class="my-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <p class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
        <span class="rounded-full bg-violet-100 px-2 py-0.5 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200">Flashcards</span>
        Spaced revision — Phase 4
    </p>
    <ul class="flex flex-wrap gap-2">
        @foreach (($payload['labels'] ?? []) as $label)
            <li class="rounded-lg border border-zinc-200 px-2.5 py-1 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">{{ $label }}</li>
        @endforeach
    </ul>
</div>
