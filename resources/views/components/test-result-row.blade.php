@props(['test' => []])

@php
    $status = $test['status'] ?? 'pending';
    $badgeClass = match ($status) {
        'pass' => 'bg-emerald-500/15 text-emerald-400 ring-emerald-500/30',
        'fail' => 'bg-rose-500/15 text-rose-400 ring-rose-500/30',
        default => 'bg-zinc-500/15 text-zinc-400 ring-zinc-500/30',
    };
    $label = match ($status) {
        'pass' => 'Pass',
        'fail' => 'Fail',
        default => 'Pending',
    };
@endphp

<div class="flex items-start gap-3 rounded-lg border border-zinc-100 px-3 py-2 dark:border-zinc-800">
    <span class="mt-0.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badgeClass }}">
        {{ $label }}
    </span>
    <div class="min-w-0">
        <p class="font-mono text-xs text-zinc-400">#{{ $test['ord'] ?? '?' }} &middot; {{ $test['type'] ?? '?' }}</p>
        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">{{ $test['message'] ?? '' }}</p>
    </div>
</div>
