@props(['title', 'hint' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700']) }}>
    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $title }}</p>
    @if ($hint !== null)
        <p class="max-w-md text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
    @endif
    @if (isset($slot) && trim($slot->toHtml()) !== '')
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
