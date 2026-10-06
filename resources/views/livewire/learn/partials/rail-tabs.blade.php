{{-- Partials cannot use @props: pass $variant ('desktop'|'mobile') and $railTab. --}}
<div
    class="flex gap-1 rounded-xl bg-zinc-100 p-1 text-sm dark:bg-zinc-900"
    role="tablist"
    aria-label="{{ __('Lesson context') }}"
>
    @foreach (['progress' => 'Progress', 'concepts' => 'Concepts', 'notes' => 'Notes', 'tutor' => 'Tutor'] as $tabKey => $tabLabel)
        <button
            type="button"
            role="tab"
            aria-selected="{{ $railTab === $tabKey ? 'true' : 'false' }}"
            wire:click="setRailTab('{{ $tabKey }}')"
            @if ($variant === 'mobile') @click="railSheet = true" @endif
            @class([
                'flex-1 rounded-lg px-2 py-1.5 text-center font-medium transition focus-visible:ring-2 focus-visible:ring-zinc-500 focus-visible:ring-offset-1 focus-visible:outline-none',
                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-zinc-50' => $railTab === $tabKey,
                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' => $railTab !== $tabKey,
            ])
        >
            {{ $tabLabel }}
        </button>
    @endforeach
</div>
