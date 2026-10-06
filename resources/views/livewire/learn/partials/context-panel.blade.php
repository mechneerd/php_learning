<div class="mt-3 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
    @if ($railTab === 'progress')
        @include('livewire.learn.partials.progress-panel', ['lesson' => $lesson, 'progress' => $progress])
    @elseif ($railTab === 'concepts')
        <p class="text-zinc-500">Concepts, prerequisites and mastery chips arrive in <strong>Phase 3</strong>.</p>
    @elseif ($railTab === 'notes')
        <p class="text-zinc-500">Notes, highlights and bookmarks arrive in <strong>Phase 5</strong>.</p>
    @else
        <livewire:learn.tutor-chat :lesson="$lessonModel" />
    @endif
</div>
