<div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
    <header class="border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('projects') }}" wire:navigate class="text-xs text-zinc-500 hover:underline">&larr; Projects</a>
            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-medium', $project->level->badgeClass()])>
                {{ $project->level->label() }}
            </span>
        </div>
        <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $project->title }}</h1>
        <p class="mt-3 text-sm leading-6 whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $project->brief }}</p>
        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
            Progress: {{ $progress['checked'] }}/{{ $progress['total'] }} tasks checked
        </p>
    </header>

    @if (! $unlocked)
        <section class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
            <p class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-200">
                <flux:icon.lock-closed class="size-4" />
                This project is locked
            </p>
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                Reach <span class="font-medium">Comfortable</span> on every concept below to unlock the tasks.
            </p>
            <ul class="mt-3 space-y-1.5">
                @foreach ($conceptRows as $concept)
                    <li class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $concept['name'] }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 font-medium',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $concept['met'],
                            'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => ! $concept['met'],
                        ])>
                            {{ $concept['level']->label() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Requirements</h2>
        <ul class="space-y-1.5 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ($requirements as $requirement)
                <li class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <span class="mt-0.5 inline-block size-3.5 shrink-0 rounded border border-zinc-300 dark:border-zinc-600"></span>
                    <span>{{ $requirement }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Tasks</h2>

        @if (! $unlocked)
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                Tasks appear once the concepts above are Comfortable.
            </div>
        @else
            <ul class="space-y-2">
                @foreach ($tasks as $task)
                    @php($isChecked = in_array($task->id, $checkedIds, true))
                    <li class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" class="mt-0.5 size-4 rounded border-zinc-300"
                                wire:click="toggleTask({{ $task->id }})" @checked($isChecked) />
                            <span class="flex-1">
                                <span @class(['text-sm text-zinc-700 dark:text-zinc-300', 'text-zinc-400 line-through' => $isChecked])>
                                    {{ $task->brief }}
                                </span>
                                @if ($task->concept_ids !== null && $task->concept_ids !== [])
                                    <span class="mt-1 block text-[11px] text-zinc-500 dark:text-zinc-400">
                                        concepts: {{ implode(', ', $task->concept_ids) }}
                                    </span>
                                @endif
                                @if ($task->solution_hint !== null)
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-[11px] text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">Hint</summary>
                                        <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $task->solution_hint }}</p>
                                    </details>
                                @endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <h2 class="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Solution</h2>

        @if (! $unlocked)
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                Available after the project is unlocked.
            </div>
        @elseif ($solutionVisible)
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm leading-6 whitespace-pre-line text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-100">
                {{ $project->solution_ref }}
            </div>
        @else
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                Available after 1 attempt &mdash; check off your first task to reveal the solution.
            </div>
        @endif
    </section>
</div>
