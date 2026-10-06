<div class="mx-auto w-full max-w-[110rem] space-y-6 px-4 py-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Honest measurement: progress comes from evidence, never from reading alone.
            </p>
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $streak }} day{{ $streak === 1 ? '' : 's' }} streak
        </p>
    </header>

    {{-- Top row: progress ring, stage tracker, due today --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Overall progress</h2>
            <div class="mt-3 flex items-center gap-4">
                <x-progress-ring :value="$progress['pct']" caption="overall" />
                <ul class="flex-1 space-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <li class="flex justify-between gap-2"><span>Lessons read (20%)</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $progress['lessonsRead'] }}/{{ $progress['lessonsTotal'] }} · {{ $progress['lessonsPct'] }}%</span></li>
                    <li class="flex justify-between gap-2"><span>Concepts mastered (40%)</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $progress['conceptsMastered'] }}/{{ $progress['conceptsTotal'] }} · {{ $progress['conceptsPct'] }}%</span></li>
                    <li class="flex justify-between gap-2"><span>Exercises solved (30%)</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $progress['exercisesCorrect'] }}/{{ $progress['exercisesTotal'] }} · {{ $progress['exercisesPct'] }}%</span></li>
                    <li class="flex justify-between gap-2"><span>Quizzes attempted (10%)</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $progress['quizzesAttempted'] }}/{{ $progress['quizzesTotal'] }} · {{ $progress['quizzesPct'] }}%</span></li>
                </ul>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Stages</h2>
            <ol class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach ($chips as $chip)
                    <x-stage-chip :stage="$chip['stage']" :locked="$chip['locked']" :current="$chip['current']" />
                @endforeach
            </ol>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Due today</h2>
            <p class="mt-2 text-4xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $dueCount }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                @if ($dueCount > 0)
                    items waiting in your revision queue.
                @elseif ($nextDueAt !== null)
                    Nothing due — your next review is {{ $nextDueAt->diffForHumans() }}.
                @else
                    Nothing due yet. Practise a little and the queue fills itself.
                @endif
            </p>
            <a href="{{ route('revision') }}"
                class="mt-3 inline-flex items-center rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-50 hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                Start revision
            </a>
        </section>
    </div>

    {{-- Bottom row: weak concepts, recent activity, streak --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Weak concepts</h2>
                <a href="{{ route('revision') }}" class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">revise →</a>
            </div>
            @if ($weak === [])
                <x-empty-state title="No weak concepts yet" hint="Concepts appear here when attempts stay below practicing." class="mt-3 !py-6" />
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($weak as $row)
                        <li>
                            <x-concept-chip :name="$row['concept']->name" :level="$row['level']" :slug="$row['concept']->slug"
                                :detail="$row['attempts'].' attempt'.($row['attempts'] === 1 ? '' : 's')" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Recent activity</h2>
            @if ($activity === [])
                <x-empty-state title="No activity yet" hint="Attempts, quizzes and card reviews show up here." class="mt-3 !py-6" />
            @else
                <ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($activity as $entry)
                        <li class="flex items-center justify-between gap-3 py-2 text-xs">
                            <div class="min-w-0">
                                <span class="mr-2 inline-flex rounded-full bg-zinc-100 px-1.5 py-0.5 font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $entry['kind'] }}</span>
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $entry['label'] }}</span>
                                <span class="block truncate text-zinc-500 dark:text-zinc-400">{{ $entry['detail'] }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <span>{{ $entry['at']->diffForHumans() }}</span>
                                @if ($entry['url'] !== null)
                                    <a href="{{ $entry['url'] }}" class="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-100">open</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Streak</h2>
            <p class="mt-2 text-4xl font-semibold text-zinc-900 dark:text-zinc-50">
                {{ $streak }}
                <span class="text-base font-normal text-zinc-500 dark:text-zinc-400">day{{ $streak === 1 ? '' : 's' }}</span>
            </p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Days in a row with at least one attempt, quiz or card review.</p>
        </section>
    </div>
</div>
