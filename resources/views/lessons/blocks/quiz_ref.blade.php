@php($payload = $block['payload'] ?? [])
<div class="my-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <p class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200">Quiz</span>
        Quiz arena — Phase 4
    </p>
    <ul class="list-decimal space-y-1.5 ps-6 text-sm text-zinc-700 dark:text-zinc-300">
        @foreach (($payload['labels'] ?? []) as $label)
            <li>{{ $label }}</li>
        @endforeach
    </ul>
</div>
