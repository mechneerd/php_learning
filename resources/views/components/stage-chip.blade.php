@props(['stage', 'locked' => false, 'current' => false, 'pct' => null])

@php
    $classes = match (true) {
        $locked => 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30',
        $current => 'border-zinc-900 bg-zinc-900 text-zinc-50 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900',
        default => 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
    };
@endphp

<li {{ $attributes->merge(['class' => 'flex min-w-36 flex-col gap-1 rounded-xl border p-3 '.$classes]) }}>
    <div class="flex items-center justify-between gap-2 text-[11px] font-medium uppercase tracking-wide opacity-70">
        <span>Stage {{ $stage->number }}</span>
        @if ($locked)
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
        @elseif ($current)
            <span class="rounded-full bg-current/20 px-1.5 py-0.5">Now</span>
        @endif
    </div>
    <p class="truncate text-sm font-medium">{{ $stage->name }}</p>
    @if ($pct !== null)
        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200/70 dark:bg-zinc-700">
            <div class="h-full rounded-full bg-current opacity-80" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</li>
