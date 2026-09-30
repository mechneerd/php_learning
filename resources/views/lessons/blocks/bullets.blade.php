@php($payload = $block['payload'] ?? [])
<ul class="list-disc space-y-1.5 ps-6 text-zinc-700 dark:text-zinc-300">
    @foreach (($payload['items'] ?? []) as $item)
        <li class="leading-6">{!! nl2br(e($item)) !!}</li>
    @endforeach
</ul>
