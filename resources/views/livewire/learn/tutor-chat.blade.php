<div class="flex flex-col gap-3">
    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
        <div>
            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                @if ($anchored !== null)
                    Anchored to: {{ $anchored }}
                @else
                    General tutor
                @endif
            </p>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Hints before answers. This is AI - check the book for page citations.</p>
        </div>
        @if ($messages !== [])
            <button type="button" wire:click="clear" wire:loading.attr="disabled"
                class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs text-zinc-500 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-400">
                New chat
            </button>
        @endif
    </header>

    <div class="flex max-h-[55vh] min-h-32 flex-col gap-3 overflow-y-auto rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/40">
        @forelse ($messages as $index => $message)
            <x-chat-bubble :role="$message['role']" :content="$message['content']" :meta="$message['meta']"
                :key="'msg-'.$index" />
        @empty
            <p class="py-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                Ask anything about the lesson - you get hints first, solutions only once you have tried.
            </p>
        @endforelse
    </div>

    @if ($notice !== null)
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
            role="alert">{{ $notice }}</p>
    @endif

    <div class="flex flex-wrap gap-1.5">
        <button type="button" wire:click="quickAction('explain_simpler')"
            class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Explain simpler</button>
        <button type="button" wire:click="quickAction('example')"
            class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Another example</button>
        <button type="button" wire:click="quickAction('quiz')"
            class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Quiz me</button>
        <button type="button" wire:click="quickAction('exercise')"
            class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Give exercise</button>
        <button type="button" wire:click="quickAction('hint')"
            class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300">Hint only</button>
    </div>

    <form wire:submit="send" class="flex items-end gap-2">
        <textarea wire:model="input" rows="2"
            placeholder="Ask the tutor... (e.g. why does this work?)"
            class="min-h-16 flex-1 resize-none rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-100"></textarea>
        <button type="submit" wire:loading.attr="disabled" wire:target="send, quickAction"
            class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-zinc-50 hover:bg-zinc-700 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
            <span wire:loading.remove wire:target="send, quickAction">Send</span>
            <span wire:loading wire:target="send, quickAction">...</span>
        </button>
    </form>

    <footer class="flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
        <span>Usage today: {{ number_format($tokensToday) }} / {{ number_format($budget) }} tokens</span>
        <span class="flex gap-1" aria-hidden="true">
            <span class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                <span class="block h-full rounded-full bg-zinc-500"
                    style="width: {{ min(100, $budget > 0 ? (int) ceil($tokensToday / $budget * 100) : 0) }}%"></span>
            </span>
        </span>
    </footer>
</div>
