@php($payload = $block['payload'] ?? [])
<div class="my-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
    <p class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Before this lesson</p>
    <ul class="space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
        @foreach (($payload['items'] ?? []) as $item)
            <li class="flex items-start gap-2">
                <svg class="mt-1 size-3.5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                <span>{!! nl2br(e($item)) !!}</span>
            </li>
        @endforeach
    </ul>
</div>
