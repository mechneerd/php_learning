@php($payload = $block['payload'] ?? [])
<div x-data="{ active: 0 }" class="my-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
    <div class="flex border-b border-zinc-200 bg-zinc-50 text-xs font-semibold tracking-wide uppercase dark:border-zinc-700 dark:bg-zinc-900">
        @foreach (($payload['tabs'] ?? []) as $index => $tab)
            <button
                type="button"
                x-on:click="active = {{ $index }}"
                x-bind:class="active === {{ $index }} ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-zinc-50' : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300'"
                class="flex-1 px-3 py-2.5 transition"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>
    <div class="px-4 py-4 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
        @foreach (($payload['tabs'] ?? []) as $index => $tab)
            <div x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif>{!! nl2br(e($tab['content'])) !!}</div>
        @endforeach
    </div>
</div>
