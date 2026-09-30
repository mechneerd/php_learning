@php($payload = $block['payload'] ?? [])
<figure class="my-2 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <figcaption class="border-b border-zinc-200 px-4 py-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-700">Output</figcaption>
    <pre class="overflow-x-auto p-4 font-mono text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $payload['text'] ?? '' }}</pre>
</figure>
