@php($payload = $block['payload'] ?? [])
<figure class="my-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
    <div class="flex aspect-video flex-col items-center justify-center gap-1 bg-zinc-100 text-sm text-zinc-500 dark:bg-zinc-900">
        <svg class="size-8 text-zinc-500 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
        <span class="font-medium">Figure {{ $payload['figure_ref'] ?? '?' }}</span>
        <span class="text-xs">
            Book figure · PDF {{ $payload['page_pdf'] ?? '—' }}
        </span>
    </div>
    @if (! empty($payload['caption']))
        <figcaption class="border-t border-zinc-100 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800">{{ $payload['caption'] }}</figcaption>
    @endif
</figure>
