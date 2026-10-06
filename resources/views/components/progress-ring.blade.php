@props(['value' => 0, 'caption' => null])

@php
    $value = max(0, min(100, (int) $value));
    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $value / 100);
@endphp

<svg {{ $attributes->merge(['class' => 'size-32']) }} viewBox="0 0 100 100" role="img" aria-label="{{ $value }}%">
    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="8" class="stroke-zinc-200 dark:stroke-zinc-700" />
    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="8" stroke-linecap="round"
        stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" transform="rotate(-90 50 50)"
        class="stroke-zinc-900 dark:stroke-zinc-100" />
    <text x="50" y="{{ $caption !== null ? 47 : 50 }}" text-anchor="middle" dominant-baseline="central"
        class="fill-zinc-900 dark:fill-zinc-100 text-xl font-semibold">{{ $value }}%</text>
    @if ($caption !== null)
        <text x="50" y="63" text-anchor="middle" dominant-baseline="central"
            class="fill-zinc-400 text-[9px] uppercase tracking-wide">{{ $caption }}</text>
    @endif
</svg>
