<?php

namespace App\Livewire\Learn;

use App\Enums\NoteKind;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Notes and bookmarks (docs/08 screen 14): grouped by kind, each
 * linking back to the lesson it was taken on.
 */
#[Title('Notes')]
class NotesList extends Component
{
    public function delete(int $noteId): void
    {
        Note::query()
            ->whereKey($noteId)
            ->where('user_id', auth()->id())
            ->delete();
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return view('livewire.learn.notes', [
            'groups' => $this->grouped($user),
        ]);
    }

    /**
     * @return list<array{kind: NoteKind, label: string, items: list<array{id: int, body: string, at: Carbon|null, url: string|null}>}>
     */
    private function grouped(User $user): array
    {
        $notes = Note::query()
            ->where('user_id', $user->id)
            ->with('noteable')
            ->orderByDesc('created_at')
            ->get();

        /** @var array<string, list<array{id: int, body: string, at: Carbon|null, url: string|null}>> $byKind */
        $byKind = [];

        foreach ($notes as $note) {
            $noteable = $note->noteable;
            $byKind[$note->kind->value][] = [
                'id' => $note->id,
                'body' => (string) $note->body,
                'at' => $note->created_at,
                'url' => $noteable instanceof Lesson ? route('lessons.show', $noteable->slug) : null,
            ];
        }

        $groups = [];

        foreach (NoteKind::cases() as $kind) {
            if (! isset($byKind[$kind->value])) {
                continue;
            }

            $groups[] = [
                'kind' => $kind,
                'label' => $kind->label(),
                'items' => $byKind[$kind->value],
            ];
        }

        return $groups;
    }
}
