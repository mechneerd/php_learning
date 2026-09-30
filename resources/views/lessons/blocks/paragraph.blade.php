@php($payload = $block['payload'] ?? [])
<div class="leading-7 text-zinc-700 dark:text-zinc-300">{!! nl2br(e($payload['markdown'] ?? $block['text'] ?? '')) !!}</div>
