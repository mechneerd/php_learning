@php
    $lesson = $lesson ?? [];
    $progress = $progress ?? null;
@endphp

@php
    $state = $progress?->state?->label() ?? 'Not opened';
    $active = $progress?->active_seconds ?? 0;
    $target = max(1, (int) ($lesson['est_minutes'] ?? 10) * 60);
    $percent = min(100, (int) round($active / $target * 100));
    $minutes = intdiv($active, 60);
    $seconds = $active % 60;
@endphp

<div class="space-y-4">
    <div>
        <div class="flex items-center justify-between text-xs text-zinc-500">
            <span class="font-medium text-zinc-700 dark:text-zinc-300">State</span>
            <span class="rounded-full bg-zinc-100 px-2 py-0.5 font-medium dark:bg-zinc-800">{{ $state }}</span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $percent }}%"></div>
        </div>
        <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $minutes }}m {{ $seconds }}s active · target {{ $lesson['est_minutes'] ?? 10 }}m</p>
    </div>

    <dl class="space-y-2 border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
        @if (! empty($lesson['citation']))
            <div class="flex justify-between gap-3">
                <dt class="text-zinc-500 dark:text-zinc-400">Source</dt>
                <dd class="text-end text-zinc-600 dark:text-zinc-300">{{ $lesson['citation'] }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-3">
            <dt class="text-zinc-500 dark:text-zinc-400">Content</dt>
            <dd class="text-end text-zinc-600 dark:text-zinc-300">{{ $lesson['source_label'] }}-sourced</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-zinc-500 dark:text-zinc-400">Position</dt>
            <dd class="text-end text-zinc-600 dark:text-zinc-300">ord {{ $lesson['ord'] }}</dd>
        </div>
    </dl>
</div>
