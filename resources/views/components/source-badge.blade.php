@props(['source' => 'ai', 'label' => null])

@php
    $label = $label ?? match ($source) {
        'book' => 'Book',
        'book_figure' => 'Book figure',
        'ai' => 'AI',
        default => ucfirst(str_replace('_', ' ', (string) $source)),
    };
    $classes = match ($source) {
        'book', 'book_figure' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        default => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$classes]) }}>{{ $label }}</span>
