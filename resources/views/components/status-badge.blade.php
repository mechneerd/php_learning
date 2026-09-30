@props(['status' => 'draft'])

@php
    $status = $status instanceof \App\Enums\ContentStatus ? $status->value : (string) $status;
    $label = \App\Enums\ContentStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status));
    $classes = match ($status) {
        'published' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
        'in_review' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
        'archived' => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$classes]) }}>{{ $label }}</span>
