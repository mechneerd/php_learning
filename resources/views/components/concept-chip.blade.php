@props(['name', 'level', 'slug' => null, 'detail' => null])

@php
    $label = $level instanceof \App\Enums\MasteryLevel ? $level->label() : ucfirst((string) $level);
    $color = $level instanceof \App\Enums\MasteryLevel ? $level->color() : '#a1a1aa';
@endphp

@if ($slug !== null)
    <a href="{{ route('concepts.show', $slug) }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs text-zinc-700 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300']) }}>
        <span class="size-2.5 rounded-full border border-zinc-400" style="background-color: {{ $color }}"></span>
        <span class="font-medium">{{ $name }}</span>
        <span class="text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
        @if ($detail !== null)
            <span class="text-zinc-500 dark:text-zinc-400">· {{ $detail }}</span>
        @endif
    </a>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300']) }}>
        <span class="size-2.5 rounded-full border border-zinc-400" style="background-color: {{ $color }}"></span>
        <span class="font-medium">{{ $name }}</span>
        <span class="text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
        @if ($detail !== null)
            <span class="text-zinc-500 dark:text-zinc-400">· {{ $detail }}</span>
        @endif
    </span>
@endif
