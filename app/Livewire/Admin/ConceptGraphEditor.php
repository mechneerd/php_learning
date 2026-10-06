<?php

namespace App\Livewire\Admin;

use App\Enums\ContentStatus;
use App\Models\Concept;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * /admin/concepts - concept graph editor: CRUD plus prerequisite edges with
 * cycle warnings and a mermaid preview (docs/08 screen table).
 */
#[Title('Concepts')]
class ConceptGraphEditor extends Component
{
    public ?int $selectedId = null;

    public string $newName = '';

    public string $newSlug = '';

    public string $newDefinition = '';

    public string $newDomain = 'oop';

    public string $addPrereqSlug = '';

    public string $updateName = '';

    public string $updateDefinition = '';

    public string $updateDomain = '';

    public ?string $notice = null;

    public ?string $error = null;

    public function select(int $id): void
    {
        $concept = Concept::query()->find($id);

        if ($concept === null) {
            return;
        }

        $this->selectedId = $id;
        $this->updateName = (string) $concept->name;
        $this->updateDefinition = (string) $concept->definition;
        $this->updateDomain = (string) $concept->skill_domain;
        $this->addPrereqSlug = '';
        $this->error = null;
        $this->notice = null;
    }

    public function create(): void
    {
        $name = trim($this->newName);
        $slug = Str::of(trim($this->newSlug) ?: Str::slug($name))->lower()->toString();

        if ($name === '' || $slug === '') {
            $this->error = 'Name and slug are required.';

            return;
        }

        if (Concept::query()->where('slug', $slug)->exists()) {
            $this->error = "Slug '{$slug}' already exists.";

            return;
        }

        $concept = Concept::query()->create([
            'slug' => $slug,
            'name' => $name,
            'definition' => trim($this->newDefinition) ?: "Plain-language definition of {$name}.",
            'skill_domain' => trim($this->newDomain) !== '' ? trim($this->newDomain) : 'oop',
            'status' => ContentStatus::InReview,
            'source' => 'ai',
        ]);

        $this->newName = '';
        $this->newSlug = '';
        $this->newDefinition = '';
        $this->select($concept->id);
        $this->notice = "Concept {$slug} created (in review).";
        $this->error = null;
    }

    public function update(): void
    {
        $concept = Concept::query()->find($this->selectedId);

        if ($concept === null) {
            return;
        }

        $concept->forceFill([
            'name' => trim($this->updateName) ?: $concept->name,
            'definition' => trim($this->updateDefinition) ?: $concept->definition,
            'skill_domain' => trim($this->updateDomain) ?: $concept->skill_domain,
        ])->save();

        $this->notice = 'Concept saved.';
    }

    public function addPrereq(): void
    {
        $concept = Concept::query()->find($this->selectedId);
        $prereq = Concept::query()->where('slug', trim($this->addPrereqSlug))->first();

        if ($concept === null || $prereq === null) {
            $this->error = 'Pick an existing concept slug as prerequisite.';

            return;
        }

        if ($prereq->id === $concept->id) {
            $this->error = 'A concept cannot be its own prerequisite.';

            return;
        }

        if ($this->wouldCycle($concept->id, $prereq->id)) {
            $this->error = "Adding {$prereq->slug} as a prerequisite would create a cycle.";

            return;
        }

        DB::table('concept_prerequisites')->updateOrInsert(
            ['concept_id' => $concept->id, 'prereq_concept_id' => $prereq->id],
            ['weight' => 1, 'source' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->addPrereqSlug = '';
        $this->notice = "Prerequisite {$prereq->slug} linked.";
        $this->error = null;
    }

    public function removePrereq(int $prereqId): void
    {
        if ($this->selectedId === null) {
            return;
        }

        DB::table('concept_prerequisites')
            ->where('concept_id', $this->selectedId)
            ->where('prereq_concept_id', $prereqId)
            ->delete();

        $this->notice = 'Prerequisite removed.';
    }

    /**
     * Walk the prerequisite edges upward from the candidate prerequisite;
     * if the concept is reachable, linking would close a cycle.
     */
    private function wouldCycle(int $conceptId, int $prereqId): bool
    {
        $seen = [];
        $queue = [$prereqId];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === $conceptId) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach (DB::table('concept_prerequisites')->where('concept_id', $current)->pluck('prereq_concept_id') as $next) {
                $queue[] = (int) $next;
            }
        }

        return false;
    }

    public function render(): View
    {
        $concepts = Concept::query()->orderBy('slug')->get();
        $selected = $this->selectedId !== null ? $concepts->firstWhere('id', $this->selectedId) : null;

        $prereqs = collect();
        $dependents = collect();

        if ($selected !== null) {
            $prereqs = DB::table('concept_prerequisites')
                ->where('concept_id', $selected->id)
                ->pluck('prereq_concept_id')
                ->map(fn ($id): ?Concept => $concepts->firstWhere('id', (int) $id))
                ->filter()
                ->values();

            $dependents = DB::table('concept_prerequisites')
                ->where('prereq_concept_id', $selected->id)
                ->pluck('concept_id')
                ->map(fn ($id): ?Concept => $concepts->firstWhere('id', (int) $id))
                ->filter()
                ->values();
        }

        $edges = DB::table('concept_prerequisites')->get();

        $lines = ['flowchart LR'];

        foreach ($edges as $edge) {
            $from = $concepts->firstWhere('id', (int) $edge->concept_id)?->slug;
            $to = $concepts->firstWhere('id', (int) $edge->prereq_concept_id)?->slug;

            if ($from !== null && $to !== null) {
                $lines[] = "    {$to} --> {$from}";
            }
        }

        if (count($lines) === 1) {
            $lines[] = '    empty[No prerequisite edges yet]';
        }

        return view('livewire.admin.concept-graph-editor', [
            'concepts' => $concepts,
            'selected' => $selected,
            'prereqs' => $prereqs,
            'dependents' => $dependents,
            'mermaid' => implode("\n", $lines),
            'edgeCount' => $edges->count(),
        ]);
    }
}
