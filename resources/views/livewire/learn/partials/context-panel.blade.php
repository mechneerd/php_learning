<div class="mt-3 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
    @if ($railTab === 'progress')
        @include('livewire.learn.partials.progress-panel', ['lesson' => $lesson, 'progress' => $progress])
    @elseif ($railTab === 'concepts')
        @if ($concepts->isEmpty())
            <p class="text-zinc-500">No concepts are linked to this lesson yet.</p>
        @else
            <ul class="space-y-3">
                @foreach ($concepts as $concept)
                    <li class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('concepts.show', $concept) }}"
                                class="font-medium text-zinc-900 hover:underline dark:text-zinc-50">{{ $concept->name }}</a>
                            @if ($concept->is_core)
                                <span class="rounded-full bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-white dark:bg-zinc-100 dark:text-zinc-900">Core</span>
                            @endif
                            @if ($concept->role === 'core')
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:bg-sky-900/60 dark:text-sky-200">In this lesson</span>
                            @endif
                        </div>
                        @if (filled($concept->definition))
                            <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $concept->definition }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @elseif ($railTab === 'notes')
        <form wire:submit="saveNote" class="space-y-2">
            <label for="note-body-{{ $panelVariant ?? 'rail' }}" class="block text-xs font-medium text-zinc-500 dark:text-zinc-400">
                New note on this lesson
            </label>
            <textarea id="note-body-{{ $panelVariant ?? 'rail' }}" wire:model="noteBody" rows="3"
                placeholder="Something worth remembering…"
                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"></textarea>
            @error('noteBody')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center gap-2">
                <label for="note-kind-{{ $panelVariant ?? 'rail' }}" class="sr-only">{{ __('Note kind') }}</label>
                <select id="note-kind-{{ $panelVariant ?? 'rail' }}" wire:model="noteKind"
                    class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs text-zinc-700 focus:border-zinc-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                    <option value="note">Note</option>
                    <option value="important">Important</option>
                    <option value="highlight">Highlight</option>
                    <option value="bookmark">Bookmark</option>
                </select>
                <button type="submit"
                    class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-500 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                    Save note
                </button>
                <a href="{{ route('notes') }}" class="ms-auto text-xs text-zinc-500 hover:underline dark:text-zinc-400">All notes</a>
            </div>
        </form>

        <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            @if ($notes->isEmpty())
                <p class="text-zinc-500">No notes on this lesson yet.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($notes as $note)
                        <li class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <p class="whitespace-pre-wrap text-zinc-800 dark:text-zinc-100">{{ $note->body }}</p>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $note->kind->label() }} · {{ $note->created_at?->diffForHumans() }}</span>
                                <button type="button" wire:click="deleteNote({{ $note->id }})"
                                    wire:confirm="Delete this note?"
                                    class="rounded-lg border border-zinc-300 px-2 py-0.5 font-medium hover:border-red-400 hover:text-red-600 dark:border-zinc-600">
                                    Delete
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <livewire:learn.tutor-chat :lesson="$lessonModel" />
    @endif
</div>
