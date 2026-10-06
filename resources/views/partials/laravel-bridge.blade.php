{{-- PHP -> Laravel bridge panel (docs/05 §4). Reused on lesson pages with
     rows already filtered to the lesson's stage via LaravelBridge::forStage(). --}}
@if (! empty($bridgeRows))
    <section class="mt-8 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
            <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">PHP → Laravel bridge</p>
            <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">How this stage&rsquo;s book concepts show up in the framework.</p>
        </div>
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @foreach ($bridgeRows as $row)
                <li class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-2.5 text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $row['core'] }}</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $row['laravel'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
