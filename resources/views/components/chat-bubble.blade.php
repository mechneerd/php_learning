@props(['role', 'content', 'meta' => []])

@php
    $isUser = $role === 'user';
    $sources = array_values(array_filter((array) ($meta['sources'] ?? [])));
@endphp

<div {{ $attributes->merge(['class' => 'flex '.($isUser ? 'justify-end' : 'justify-start')]) }}>
    <div @class([
        'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm',
        'bg-zinc-900 text-zinc-50 dark:bg-zinc-100 dark:text-zinc-900' => $isUser,
        'border border-zinc-200 bg-white text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => ! $isUser,
    ])>
        @unless ($isUser)
            <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-950 dark:text-violet-300">AI</span>
                @foreach ($sources as $source)
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $source }}</span>
                @endforeach
            </div>
        @endunless

        <p class="whitespace-pre-line">{{ $content }}</p>
    </div>
</div>
