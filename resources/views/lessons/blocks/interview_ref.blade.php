@php($payload = $block['payload'] ?? [])
<div class="my-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <p class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
        <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">Interview</span>
        Question set — Phase 9
    </p>
    <ol class="list-decimal space-y-2 ps-6 text-sm text-zinc-700 dark:text-zinc-300">
        @foreach (($payload['labels'] ?? []) as $label)
            <li class="leading-6">{{ $label }}</li>
        @endforeach
    </ol>
</div>
