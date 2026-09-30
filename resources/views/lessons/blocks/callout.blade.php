@php
    $payload = $block['payload'] ?? [];
    $variant = $payload['variant'] ?? 'info';
    $styles = match ($variant) {
        'tip' => 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100',
        'warn' => 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100',
        'danger' => 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/50 dark:text-red-100',
        default => 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-100',
    };
@endphp
<div class="rounded-xl border-s-4 px-4 py-3 text-sm leading-6 {{ $styles }}">
    {!! nl2br(e($payload['text'] ?? $block['text'] ?? '')) !!}
    <x-citation-line :citation="$block['citation'] ?? null" class="mt-1 block text-xs opacity-70" />
</div>
