@php($payload = $block['payload'] ?? [])
<figure class="group/code my-2 overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950">
    <figcaption class="flex items-center justify-between border-b border-zinc-800 px-4 py-2 text-xs text-zinc-400">
        <span class="font-mono uppercase">{{ $payload['lang'] ?? 'php' }}</span>
        <x-citation-line :citation="$block['citation'] ?? null" />
    </figcaption>
    <pre class="overflow-x-auto p-4 text-sm leading-6"><code class="language-{{ $payload['lang'] ?? 'php' }}">{{ $payload['code'] ?? '' }}</code></pre>
</figure>
