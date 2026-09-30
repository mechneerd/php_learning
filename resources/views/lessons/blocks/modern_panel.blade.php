@php($payload = $block['payload'] ?? [])
<div x-data="{ panel: 'book' }" class="my-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
    <div class="flex border-b border-zinc-200 bg-zinc-50 text-xs font-semibold tracking-wide uppercase dark:border-zinc-700 dark:bg-zinc-900">
        @foreach (['book' => 'Book teaches', 'modern' => 'Modern PHP', 'why' => 'Why'] as $key => $label)
            <button
                type="button"
                x-on:click="panel = '{{ $key }}'"
                x-bind:class="panel === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-zinc-50' : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300'"
                class="flex-1 px-3 py-2.5 transition"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
    <div class="px-4 py-4 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
        <p x-show="panel === 'book'">{!! nl2br(e($payload['book'] ?? '')) !!}</p>
        <p x-show="panel === 'modern'" x-cloak>{!! nl2br(e($payload['modern'] ?? '')) !!}</p>
        <p x-show="panel === 'why'" x-cloak>{!! nl2br(e($payload['why'] ?? '')) !!}</p>
    </div>
</div>
