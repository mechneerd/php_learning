<div class="space-y-6">
    @if ($lessonId === null)
        <div>
            <flux:heading level="2">{{ __('Lessons') }}</flux:heading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Edit lesson metadata and content blocks. Every save moves the lesson to in review - nothing publishes itself.') }}
            </p>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Source') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($lessons as $lesson)
                    <flux:table.row :key="$lesson->id">
                        <flux:table.cell>{{ $lesson->title }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$lesson->status->value === 'published' ? 'green' : ($lesson->status->value === 'in_review' ? 'amber' : 'gray')">
                                {{ $lesson->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $lesson->source->value }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" wire:click="select({{ $lesson->id }})">{{ __('Edit') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('No lessons yet - run content:generate.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @else
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading level="2">{{ $title !== '' ? $title : 'Lesson #'.$lessonId }}</flux:heading>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    <flux:badge :color="$status === 'published' ? 'green' : ($status === 'in_review' ? 'amber' : 'gray')">{{ $status }}</flux:badge>
                </p>
            </div>
            <div class="flex gap-2">
                <flux:button wire:click="backToList">{{ __('Back') }}</flux:button>
                <flux:button wire:click="regenerate">{{ __('Regenerate from book') }}</flux:button>
            </div>
        </div>

        @if ($notice)
            <flux:callout variant="success" icon="check-circle" class="mb-0">{{ $notice }}</flux:callout>
        @endif
        @if ($error)
            <flux:callout variant="danger" icon="exclamation-circle" class="mb-0">{{ $error }}</flux:callout>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <flux:heading size="sm">{{ __('Details') }}</flux:heading>
                    <div class="mt-3 space-y-3">
                        <flux:input wire:model="title" :label="__('Title')" />
                        <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" />
                        <flux:input wire:model="estMinutes" type="number" :label="__('Estimated minutes')" />
                        <flux:button variant="primary" wire:click="saveLessonMeta">{{ __('Save details') }}</flux:button>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <flux:heading size="sm">{{ __('Blocks') }}</flux:heading>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['heading', 'paragraph', 'bullets', 'callout', 'code', 'output', 'table', 'book_quote', 'modern_panel', 'prereq_list', 'exercise_ref', 'quiz_ref', 'card_refs', 'image'] as $type)
                            <flux:button size="sm" variant="ghost" wire:click="addBlock('{{ $type }}')">+ {{ $type }}</flux:button>
                        @endforeach
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($blocks as $index => $block)
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="gray">{{ $index + 1 }}</flux:badge>
                                        <flux:badge color="blue">{{ $block['type'] }}</flux:badge>
                                    </div>
                                    <div class="flex gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $index }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="move({{ $index }}, -1)" :disabled="$index === 0">↑</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="move({{ $index }}, 1)" :disabled="$index === count($blocks) - 1">↓</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="deleteBlock({{ $index }})">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>

                                @if ($editIndex === $index)
                                    <textarea wire:model="editJson" rows="8" class="mt-2 w-full rounded-lg border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-black/40"></textarea>
                                    <div class="mt-2 flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="saveBlock">{{ __('Save block') }}</flux:button>
                                        <flux:button size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                    </div>
                                @else
                                    <pre class="mt-2 max-h-24 overflow-auto whitespace-pre-wrap break-all text-xs text-zinc-500 dark:text-zinc-400">{{ $block['payload_json'] }}</pre>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No blocks yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <flux:heading size="sm">{{ __('Preview') }}</flux:heading>
                @if ($preview !== null)
                    <div class="mt-3 space-y-3">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $preview['lesson']['title'] ?? $title }}</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $preview['lesson']['summary'] ?? $summary }}</p>
                        @foreach ($preview['blocks'] as $block)
                            @switch($block['type'])
                                @case('heading')
                                    <h4 class="mt-3 font-semibold text-zinc-900 dark:text-white">{{ $block['payload']['text'] ?? '' }}</h4>
                                    @break
                                @case('paragraph')
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $block['payload']['markdown'] ?? '' }}</p>
                                    @break
                                @case('bullets')
                                    <ul class="list-disc pl-5 text-sm text-zinc-700 dark:text-zinc-300">
                                        @foreach (($block['payload']['items'] ?? []) as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                    @break
                                @case('code')
                                    <pre class="overflow-auto rounded-lg bg-zinc-900 p-3 text-xs text-zinc-100"><code>{{ $block['payload']['code'] ?? '' }}</code></pre>
                                    @break
                                @case('callout')
                                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200">{{ $block['payload']['text'] ?? '' }}</div>
                                    @break
                                @default
                                    <div class="rounded-lg border border-dashed border-zinc-300 p-2 text-xs text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                                        {{ $block['type'] }}
                                    </div>
                            @endswitch
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing to preview.') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
