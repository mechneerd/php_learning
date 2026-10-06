<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading level="2">{{ __('Import Jobs') }}</flux:heading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Pipeline A telemetry: generation ledger, token budget and queue depth.') }}
            </p>
        </div>
        @if ($failed > 0)
            <flux:button variant="danger" wire:click="clearFailed">{{ __('Clear failed rows') }}</flux:button>
        @endif
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" class="mb-0">{{ $notice }}</flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Queued jobs') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $queued }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $reserved }} {{ __('reserved') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Failed generations') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold {{ $failed > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white' }}">{{ $failed }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('retry available') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tokens today') }}</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($todayTokens) }}</p>
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">/ {{ number_format($budget) }} {{ __('budget') }}</flux:text>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Usage') }}</flux:text>
            @php $pct = $budget > 0 ? min(100, (int) round($todayTokens / $budget * 100)) : 0; @endphp
            <div class="mt-2 h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-2 rounded-full {{ $pct > 80 ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div>
            </div>
            <flux:text class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $pct }}%</flux:text>
        </flux:card>
    </div>

    <div class="flex gap-2">
        @foreach (['all' => __('All'), 'queued' => __('Queued'), 'running' => __('Running'), 'done' => __('Done'), 'failed' => __('Failed')] as $value => $label)
            <flux:button size="sm" :variant="$filter === $value ? 'primary' : 'ghost'" wire:click="$set('filter', '{{ $value }}')">{{ $label }}</flux:button>
        @endforeach
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>{{ __('Task') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Entity') }}</flux:table.column>
            <flux:table.column>{{ __('Tokens in/out') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($generations as $generation)
                <flux:table.row :key="$generation->id">
                    <flux:table.cell>{{ $generation->id }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $generation->stage }}
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $generation->prompt_version }})</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$generation->status->value === 'done' ? 'green' : ($generation->status->value === 'failed' ? 'red' : ($generation->status->value === 'running' ? 'blue' : 'gray'))">
                            {{ $generation->status->value }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $generation->entity_type }}{{ $generation->entity_id !== null ? ' #'.$generation->entity_id : '' }}</flux:table.cell>
                    <flux:table.cell>{{ $generation->tokens_in }} / {{ $generation->tokens_out }}</flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $generation->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($generation->status->value === 'failed')
                            <flux:button size="sm" wire:click="retry({{ $generation->id }})">{{ __('Retry') }}</flux:button>
                        @endif
                        @if ($generation->error)
                            <span class="ml-1 max-w-40 truncate align-middle text-xs text-red-500" title="{{ $generation->error }}">{{ $generation->error }}</span>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No generations yet - run content:generate.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
