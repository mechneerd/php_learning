<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading level="2">{{ __('Review Queue') }}</flux:heading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('AI drafts wait here. Approve to publish (ContentVersion is recorded), reject to send back to draft, or regenerate from the book.') }}
            </p>
        </div>
        @if ($diffSummary !== null)
            <flux:badge color="amber">{{ $diffSummary }}</flux:badge>
        @endif
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" class="mb-0">{{ $notice }}</flux:callout>
    @endif

    @if ($selectedType !== null)
        <div class="rounded-xl border border-amber-300/60 bg-amber-50/50 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="sm">{{ ucfirst(str_replace('_', ' ', $selectedType)) }} #{{ $selectedId }}</flux:heading>
                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                    <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                    <flux:button wire:click="clear">{{ __('Close') }}</flux:button>
                </div>
            </div>

            <div class="mt-3 max-h-96 overflow-auto rounded-lg bg-white/70 p-3 font-mono text-xs dark:bg-black/40">
                @forelse ($diff as $row)
                    <div @class([
                        'whitespace-pre-wrap break-all',
                        'text-emerald-700 dark:text-emerald-400' => $row['op'] === '+',
                        'text-red-700 dark:text-red-400' => $row['op'] === '-',
                        'text-zinc-500 dark:text-zinc-400' => $row['op'] === '=',
                    ])>
                        <span class="select-none opacity-60">{{ $row['op'] }}</span> {{ $row['line'] }}
                    </div>
                @empty
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No differences from the last approved version.') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Lesson') }}</flux:table.column>
            <flux:table.column>{{ __('Stage') }}</flux:table.column>
            <flux:table.column>{{ __('Pages') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($lessons as $lesson)
                <flux:table.row :key="'l'.$lesson->id">
                    <flux:table.cell>{{ $lesson->title }}</flux:table.cell>
                    <flux:table.cell>{{ $lesson->stage?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($lesson->page_printed_from !== null)
                            {{ $lesson->page_printed_from }}–{{ $lesson->page_printed_to }}
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="select('lesson', {{ $lesson->id }})">{{ __('Diff') }}</flux:button>
                            @if ($lesson->section_id !== null)
                                <flux:button size="sm" variant="ghost" wire:click="regenerate({{ $lesson->id }})">{{ __('Regenerate') }}</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No lessons waiting for review.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($others->isNotEmpty())
        <flux:heading level="3">{{ __('Other in-review content') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('Label') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($others as $row)
                    <flux:table.row :key="$row['type'].'-'.$row['id']">
                        <flux:table.cell>{{ $row['type'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['id'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" wire:click="select('{{ $row['type'] }}', {{ $row['id'] }})">{{ __('Diff') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
