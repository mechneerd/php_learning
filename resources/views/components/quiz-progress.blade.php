@props(['total' => 0, 'index' => 0, 'answeredCount' => 0])

<div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
     aria-label="Quiz progress">
    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
        Question {{ min($index + 1, max($total, 1)) }} of {{ $total }}
    </span>
    <ol class="flex gap-1.5">
        @for ($i = 0; $i < $total; $i++)
            <li @class([
                'size-2.5 rounded-full transition',
                'bg-zinc-900 dark:bg-zinc-100' => $i === $index,
                'bg-emerald-400' => $i < $index && $i < $answeredCount,
                'bg-zinc-200 dark:bg-zinc-700' => $i > $index,
            ]) aria-label="Question {{ $i + 1 }}"></li>
        @endfor
    </ol>
</div>
