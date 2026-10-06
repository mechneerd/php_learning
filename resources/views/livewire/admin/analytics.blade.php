<div class="space-y-6">
    <div>
        <flux:heading level="2">{{ __('Analytics') }}</flux:heading>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Content coverage per stage, exercise pass rates and AI spend from the generation ledger.') }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Lessons published') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $stats['lessons_published'] }} / {{ $stats['lessons_total'] }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $stats['stages'] }} {{ __('stages') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Exercise pass rate') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $stats['pass_rate'] }}%</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($stats['attempts']) }} {{ __('attempts') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('AI spend (all time)') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">${{ number_format($stats['spend'], 4) }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($stats['generations']) }} {{ __('generations') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Failed generations') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold {{ $stats['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white' }}">{{ $stats['failed'] }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('see import jobs') }}</flux:text>
        </flux:card>
    </div>

    <section aria-labelledby="coverage-heading">
        <flux:heading id="coverage-heading" level="3" size="sm">{{ __('Coverage per stage') }}</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>{{ __('Stage') }}</flux:table.column>
                <flux:table.column>{{ __('Lessons') }}</flux:table.column>
                <flux:table.column>{{ __('Exercises') }}</flux:table.column>
                <flux:table.column>{{ __('Published') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($stageRows as $row)
                    <flux:table.row>
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-zinc-50">{{ $row['number'] }} · {{ $row['name'] }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['lessons_published'] }} / {{ $row['lessons_total'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['exercises_published'] }} / {{ $row['exercises_total'] }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $published = $row['lessons_published'] + $row['exercises_published'];
                                $total = $row['lessons_total'] + $row['exercises_total'];
                                $coverage = $total > 0 ? (int) round($published / $total * 100) : 0;
                            @endphp
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-2 rounded-full {{ $coverage === 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $coverage }}%"></div>
                                </div>
                                <span class="text-xs text-zinc-500">{{ $coverage }}%</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell class="text-zinc-500">{{ __('No stages yet.') }}</flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    <section aria-labelledby="pass-rate-heading">
        <flux:heading id="pass-rate-heading" level="3" size="sm">{{ __('Exercise pass rates (top 10 by attempts)') }}</flux:heading>
        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>{{ __('Exercise') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Passed') }}</flux:table.column>
                <flux:table.column>{{ __('Rate') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($exerciseRows as $row)
                    <flux:table.row>
                        <flux:table.cell>
                            <span class="text-zinc-900 dark:text-zinc-50">{{ $row['prompt'] }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['attempts'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['correct'] }}</flux:table.cell>
                        <flux:table.cell>
                            @class([
                                'font-medium',
                                'text-emerald-600 dark:text-emerald-400' => $row['rate'] >= 80,
                                'text-amber-600 dark:text-amber-400' => $row['rate'] >= 50 && $row['rate'] < 80,
                                'text-red-600 dark:text-red-400' => $row['rate'] < 50,
                            ])
                        >{{ $row['rate'] }}%</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell class="text-zinc-500">{{ __('No exercise attempts recorded yet.') }}</flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    <section aria-labelledby="spend-heading">
        <flux:heading id="spend-heading" level="3" size="sm">{{ __('AI spend — last 30 days') }}</flux:heading>
        <flux:card class="mt-2 p-4">
            @if (array_sum(array_column($spendDays, 'cost')) > 0)
                <div class="flex h-40 items-end gap-1" role="img" aria-label="{{ __('Daily AI spend for the last 30 days') }}">
                    @foreach ($spendDays as $day)
                        <div
                            class="group relative flex-1"
                            title="{{ $day['day'] }} · ${{ number_format($day['cost'], 4) }} · {{ number_format($day['tokens']) }} tokens"
                        >
                            <div
                                class="w-full rounded-t {{ $day['pct'] > 0 ? 'bg-amber-500' : 'bg-zinc-200 dark:bg-zinc-700' }}"
                                style="height: {{ max(2, $day['pct']) }}%"
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-500">
                    <span>{{ $spendDays[0]['label'] }}</span>
                    <span>{{ $spendDays[array_key_last($spendDays)]['label'] }}</span>
                </div>
            @else
                <p class="py-6 text-center text-sm text-zinc-500">{{ __('No AI generations recorded in the last 30 days.') }}</p>
            @endif
        </flux:card>
    </section>
</div>
