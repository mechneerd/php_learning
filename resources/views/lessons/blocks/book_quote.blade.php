@php($payload = $block['payload'] ?? [])
<blockquote class="my-2 rounded-r-xl border-s-4 border-zinc-400 bg-zinc-50 px-4 py-3 text-sm leading-7 text-zinc-600 italic dark:bg-zinc-900 dark:text-zinc-300">
    {!! nl2br(e($payload['text'] ?? '')) !!}
    <footer class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 not-italic">
        {{ $payload['attribution'] ?? $block['citation'] ?? 'From the book' }}
    </footer>
</blockquote>
