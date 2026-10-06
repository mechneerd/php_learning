<div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
    <header class="border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Interview</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Answer out loud or in the box, reveal the model answer, then grade yourself honestly.
        </p>
    </header>

    @if ($topics->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="selectTopic(null)"
                @class([
                    'rounded-full border px-3 py-1 text-xs font-medium transition',
                    'border-zinc-900 bg-zinc-900 text-zinc-50 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $topic === null,
                    'border-zinc-300 text-zinc-600 hover:border-zinc-500 dark:border-zinc-700 dark:text-zinc-400' => $topic !== null,
                ])>
                All topics
            </button>
            @foreach ($topics as $topicName)
                <button type="button" wire:click="selectTopic('{{ $topicName }}')"
                    @class([
                        'rounded-full border px-3 py-1 text-xs font-medium transition',
                        'border-zinc-900 bg-zinc-900 text-zinc-50 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $topic === $topicName,
                        'border-zinc-300 text-zinc-600 hover:border-zinc-500 dark:border-zinc-700 dark:text-zinc-400' => $topic !== $topicName,
                    ])>
                    {{ ucfirst($topicName) }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($question === null)
        <x-empty-state title="No questions yet"
            hint="Interview questions arrive with the content pipeline — check back soon." />
    @else
        <article class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ ucfirst($question->topic) }}
                </span>
                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ $question->difficulty->label() }}
                </span>
            </div>

            <h2 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ $question->question }}</h2>

            <div class="mt-4">
                <label for="attempt" class="block text-xs font-medium text-zinc-500">Your answer</label>
                <textarea id="attempt" rows="4" wire:model="attempt"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    placeholder="Say it out loud first, then type the key points..."></textarea>
            </div>

            @if (! $revealed)
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="reveal"
                        class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-50 hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                        Reveal model answer
                    </button>
                    <button type="button" wire:click="next"
                        class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:border-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        Skip &amp; next
                    </button>
                </div>
            @else
                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950">
                    <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Model answer</p>
                    <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $question->model_answer }}</p>

                    @if ($question->follow_ups !== null && $question->follow_ups !== [])
                        <p class="mt-3 text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Follow-ups</p>
                        <ul class="mt-1 list-disc space-y-1 ps-5 text-sm text-zinc-600 dark:text-zinc-400">
                            @foreach ($question->follow_ups as $followUp)
                                <li>{{ $followUp }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="mt-4">
                    <p class="text-xs font-medium text-zinc-500">Grade yourself</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" wire:click="grade('confident')"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                            Confident
                        </button>
                        <button type="button" wire:click="grade('shaky')"
                            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600">
                            Shaky
                        </button>
                        <button type="button" wire:click="grade('missed')"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                            Missed
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400">Grading queues this question into your revision set.</p>
                </div>
            @endif
        </article>
    @endif
</div>
