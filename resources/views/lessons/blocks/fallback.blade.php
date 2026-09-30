<div class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
    <p class="font-medium">Unsupported block type: {{ $block['type'] ?? 'unknown' }}</p>
    @if (! empty($block['text']))
        <p class="mt-1">{!! nl2br(e($block['text'])) !!}</p>
    @endif
</div>
