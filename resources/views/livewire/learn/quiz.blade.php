<div class="mx-auto w-full max-w-[48rem] space-y-6 px-4 py-6">
    <header class="border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Quiz</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            @if ($lesson)
                <a href="{{ route('lessons.show', $lesson->slug) }}" class="underline hover:text-zinc-700 dark:hover:text-zinc-200">{{ $lesson->title }}</a>
                &middot;
            @endif
            {{ $total }} question(s) &middot; one at a time, no going back.
        </p>
    </header>

    @if ($report)
        {{-- Final report --}}
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-100 px-6 py-5 dark:border-zinc-800">
                <div class="flex items-center gap-4">
                    <div class="grid size-20 place-items-center rounded-full border-4 border-emerald-400">
                        <span class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ round($report['score']) }}%</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $report['correct'] }} of {{ $report['total'] }} correct
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-400">
                            {{ $report['duration_sec'] }}s
                            @if ($report['is_best'])
                                &middot; <span class="font-medium text-emerald-500">new best score</span>
                            @endif
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="retry"
                    class="rounded-lg border border-zinc-200 px-4 py-1.5 text-sm font-medium text-zinc-600 transition hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300"
                >
                    Re-quiz
                </button>
            </div>

            @if ($report['weak_concepts'] !== [])
                <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    <p class="text-xs font-semibold tracking-wide text-rose-500 uppercase">Weak concepts</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($report['weak_concepts'] as $slug)
                            <a href="{{ route('concepts.show', $slug) }}"
                               class="rounded-full bg-rose-500/10 px-3 py-1 text-xs font-medium text-rose-500 ring-1 ring-inset ring-rose-500/30 hover:bg-rose-500/20">
                                {{ $slug }}
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('practice') }}"
                       class="mt-3 inline-block text-sm font-medium text-zinc-900 underline dark:text-zinc-100">
                        Go to targeted practice &rarr;
                    </a>
                </div>
            @endif

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($report['review'] as $row)
                    <li class="px-6 py-4">
                        <div class="flex items-start gap-3">
                            <span @class([
                                'mt-0.5 size-5 shrink-0 rounded-full text-center text-[0.7rem] leading-5 font-bold',
                                'bg-emerald-500/15 text-emerald-500' => $row['correct'],
                                'bg-rose-500/15 text-rose-500' => ! $row['correct'],
                            ])>{{ $row['correct'] ? '&#10003;' : '&#10005;' }}</span>
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm text-zinc-800 dark:text-zinc-200">{!! nl2br(e($row['stem'])) !!}</p>
                                <p class="text-xs text-zinc-500">
                                    Your answer: <span class="{{ $row['correct'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $row['given'] ?? '(blank)' }}</span>
                                    @if (! $row['correct'] && $row['correct_answer'])
                                        &middot; Correct: <span class="text-emerald-500">{{ $row['correct_answer'] }}</span>
                                    @endif
                                </p>
                                @if ($row['explanation'])
                                    <p class="text-xs text-zinc-400">{{ $row['explanation'] }}</p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @elseif ($current)
        {{-- Progress --}}
        <x-quiz-progress :total="$total" :index="$index" :answered-count="$answeredCount" />

        {{-- Question card --}}
        <section class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                <span class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">
                    {{ $current->type->label() }}
                </span>
                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ $current->difficulty }}
                </span>
            </div>

            <div class="space-y-4 px-5 py-5">
                <div class="text-sm leading-relaxed text-zinc-800 dark:text-zinc-200">
                    {!! nl2br(e($current->stem)) !!}
                </div>

                @if ($lastCorrect === null)
                    @if ($current->type->usesOptions())
                        <fieldset class="space-y-2">
                            <legend class="sr-only">Options</legend>
                            @foreach ($current->options as $option)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 px-4 py-3 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                                    <input
                                        type="radio"
                                        name="option"
                                        value="{{ $option->id }}"
                                        wire:model.live="option_id"
                                        class="mt-1 accent-zinc-900 dark:accent-zinc-100"
                                    >
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{!! nl2br(e($option->text)) !!}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @else
                        <textarea
                            wire:model="answer_text"
                            rows="3"
                            class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200"
                            placeholder="Your answer&hellip;"
                        ></textarea>
                    @endif

                    @error('option_id') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    @error('answer_text') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

                    <button
                        type="button"
                        wire:click="submit"
                        wire:loading.attr="disabled"
                        class="mt-2 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                    >
                        <span wire:loading.remove wire:target="submit">Submit answer</span>
                        <span wire:loading wire:target="submit">Checking&hellip;</span>
                    </button>
                @else
                    {{-- Instant feedback --}}
                    <div @class([
                        'rounded-lg border px-4 py-3',
                        'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' => $lastCorrect,
                        'border-rose-300 bg-rose-50 dark:border-rose-700 dark:bg-rose-950/40' => ! $lastCorrect,
                    ])>
                        <p @class([
                            'text-sm font-semibold',
                            'text-emerald-700 dark:text-emerald-300' => $lastCorrect,
                            'text-rose-700 dark:text-rose-300' => ! $lastCorrect,
                        ])>
                            {{ $lastCorrect ? 'Correct' : 'Not quite' }}
                        </p>
                        @if ($lastFeedback !== '')
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $lastFeedback }}</p>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="next"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                    >
                        {{ $answeredCount >= $total ? 'See report' : 'Next question' }}
                    </button>
                @endif
            </div>
        </section>
    @else
        <p class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
            No quiz questions here yet. Seed them with <code>php artisan db:seed --class=QuizSeeder</code>.
        </p>
    @endif
</div>
