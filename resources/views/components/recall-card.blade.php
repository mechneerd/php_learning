@props(['title' => 'Explain it', 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900']) }}>
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $title }}</h3>
            @if ($hint !== null)
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
            @endif
        </div>
        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-950 dark:text-amber-300">Recall</span>
    </div>

    {{ $slot }}
</div>
