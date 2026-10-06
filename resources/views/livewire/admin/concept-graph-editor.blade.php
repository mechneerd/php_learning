<div class="space-y-6">
    <div>
        <flux:heading level="2">{{ __('Concept Graph') }}</flux:heading>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Concepts and their prerequisite edges. Cycles are rejected: the dependency graph must stay acyclic.') }}
        </p>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" class="mb-0">{{ $notice }}</flux:callout>
    @endif
    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" class="mb-0">{{ $error }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Slug') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Domain') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($concepts as $concept)
                        <flux:table.row :key="$concept->id" wire:key="concept-{{ $concept->id }}">
                            <flux:table.cell class="font-mono text-xs">{{ $concept->slug }}</flux:table.cell>
                            <flux:table.cell>{{ $concept->name }}</flux:table.cell>
                            <flux:table.cell>{{ $concept->skill_domain }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" wire:click="select({{ $concept->id }})">
                                    {{ $selectedId === $concept->id ? __('Selected') : __('Select') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __('No concepts - run content:generate or book:detect.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <flux:heading size="sm">{{ __('New concept') }}</flux:heading>
                <div class="mt-3 space-y-3">
                    <flux:input wire:model="newName" :label="__('Name')" />
                    <flux:input wire:model="newSlug" :label="__('Slug (optional)')" placeholder="auto from name" />
                    <flux:textarea wire:model="newDefinition" :label="__('Definition')" rows="2" />
                    <flux:input wire:model="newDomain" :label="__('Skill domain')" />
                    <flux:button variant="primary" wire:click="create">{{ __('Create concept') }}</flux:button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            @if ($selected !== null)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <flux:heading size="sm">{{ __('Edit') }}: {{ $selected->name }}</flux:heading>
                    <div class="mt-3 space-y-3">
                        <flux:input wire:model="updateName" :label="__('Name')" />
                        <flux:textarea wire:model="updateDefinition" :label="__('Definition')" rows="3" />
                        <flux:input wire:model="updateDomain" :label="__('Skill domain')" />
                        <flux:button variant="primary" wire:click="update">
                            {{ __('Save concept') }}
                        </flux:button>
                    </div>

                    <div class="mt-4">
                        <flux:heading size="sm">{{ __('Prerequisites of this concept') }}</flux:heading>
                        <div class="mt-2 space-y-1">
                            @forelse ($prereqs as $prereq)
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm dark:border-white/10">
                                    <span class="font-mono text-xs">{{ $prereq->slug }}</span>
                                    <flux:button size="sm" variant="danger" wire:click="removePrereq({{ $prereq->id }})">{{ __('Remove') }}</flux:button>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No prerequisites yet.') }}</p>
                            @endforelse
                        </div>

                        <div class="mt-3 flex gap-2">
                            <flux:input wire:model="addPrereqSlug" :label="__('Add prerequisite slug')" />
                            <flux:button class="self-end" wire:click="addPrereq">{{ __('Link') }}</flux:button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <flux:heading size="sm">{{ __('Depends on this concept') }}</flux:heading>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($dependents as $dependent)
                                <flux:badge color="blue" wire:key="dep-{{ $dependent->id }}">{{ $dependent->slug }}</flux:badge>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing depends on it yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Select a concept to edit it and its prerequisites.') }}</p>
            @endif

            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('Graph preview') }}</flux:heading>
                    <flux:badge color="gray">{{ $edgeCount }} {{ __('edges') }}</flux:badge>
                </div>
                <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs text-zinc-100">{{ $mermaid }}</pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Rendered as mermaid on the path screen: prerequisite --> concept.') }}
                </p>
            </div>
        </div>
    </div>
</div>
