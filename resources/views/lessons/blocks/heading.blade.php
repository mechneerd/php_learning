@php($payload = $block['payload'] ?? [])
<h2 id="block-{{ $block['id'] }}" class="mt-8 mb-3 text-xl font-semibold text-zinc-900 first:mt-0 dark:text-zinc-50">
    {{ $payload['text'] ?? $block['text'] ?? '' }}
</h2>
