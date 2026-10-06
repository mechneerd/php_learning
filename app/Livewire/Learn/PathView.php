<?php

namespace App\Livewire\Learn;

use App\Enums\MasteryLevel;
use App\Enums\ProgressState;
use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Stage;
use App\Services\Content\MermaidSanitizer;
use App\Services\Learning\EvidenceBuilder;
use App\Services\Learning\GateEvaluator;
use App\Services\Learning\GateEvidence;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection as BaseCollection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The learning path screen: stage rail, concept graph (Mermaid), gate panel
 * with pass/fail chips and the next lesson recommendation. Also serves
 * /concepts/{slug} by pre-selecting a node (MVP: one component, two routes).
 *
 * Mastery evidence comes from EvidenceBuilder (concept_mastery, quizzes,
 * exercise attempts), so gates and node colors reflect live progress.
 */
#[Title('Learning Path')]
class PathView extends Component
{
    public ?string $selected = null;

    public function mount(?Concept $concept = null): void
    {
        $this->selected = $concept?->slug;
    }

    public function select(?string $slug): void
    {
        if ($slug === null || $slug === $this->selected) {
            $this->selected = null;

            return;
        }

        if (Concept::query()->where('slug', $slug)->exists()) {
            $this->selected = $slug;
        }
    }

    public function render(GateEvaluator $evaluator, MermaidSanitizer $sanitizer, EvidenceBuilder $evidenceBuilder): View
    {
        $stages = Stage::query()->orderBy('number')->get();
        $evidence = $evidenceBuilder->forUser((int) auth()->id());

        $lessonRead = $this->readCountsByStage();
        $totals = $this->lessonTotalsByStage();

        $rail = [];
        $nextGate = null;
        $gatedStages = 0;

        foreach ($stages as $stage) {
            $result = $evaluator->evaluate($stage->gate_rules, $evidence);
            $total = $totals[$stage->id] ?? 0;
            $read = $lessonRead->get($stage->id, 0);

            $locked = $result->isGated() && ! $result->passed();

            if ($result->isGated()) {
                $gatedStages++;
            }

            $rail[] = [
                'stage' => $stage,
                'pct' => $total > 0 ? (int) round($read * 100 / $total) : null,
                'gate' => $result,
                'locked' => $locked,
            ];

            if ($nextGate === null && $result->isGated() && ! $result->passed()) {
                $nextGate = ['stage' => $stage, 'result' => $result];
            }
        }

        $recommendation = $this->recommendation();
        $recommendedIds = [];

        if ($recommendation !== null) {
            foreach ($recommendation->concepts()->wherePivot('role', 'core')->get() as $recommended) {
                $recommendedIds[] = $recommended->id;
            }
        }

        $concept = $this->selected !== null
            ? Concept::query()
                ->with(['prerequisites:id,slug,name', 'dependents:id,slug,name', 'lessons:id,title,slug,status'])
                ->where('slug', $this->selected)
                ->first()
            : null;

        $mermaid = $sanitizer->sanitize($this->buildMermaid($concept?->id, $recommendedIds, $evidence));

        return view('livewire.learn.path', [
            'rail' => $rail,
            'gatedStages' => $gatedStages,
            'nextGate' => $nextGate,
            'recommendation' => $recommendation,
            'concept' => $concept,
            'mermaid' => $mermaid,
            'levels' => MasteryLevel::cases(),
            'coreConcepts' => Concept::query()
                ->where('is_core', true)
                ->orderBy('name')
                ->get(['id', 'slug', 'name']),
            'conceptCount' => Concept::query()->count(),
        ]);
    }

    /**
     * Published lesson totals per stage.
     *
     * @return array<int, int>
     */
    private function lessonTotalsByStage(): array
    {
        return Lesson::query()
            ->published()
            ->selectRaw('stage_id, COUNT(*) as total')
            ->groupBy('stage_id')
            ->pluck('total', 'stage_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Progress states that count as "read" (rank >= ProgressState::Read).
     *
     * @return list<string>
     */
    private function readStateValues(): array
    {
        return array_values(array_map(
            fn (ProgressState $state) => $state->value,
            array_filter(
                ProgressState::cases(),
                fn (ProgressState $state) => $state->rank() >= ProgressState::Read->rank(),
            ),
        ));
    }

    /**
     * Read published lesson counts per stage, keyed by stage id.
     *
     * @return BaseCollection<int, int>
     */
    private function readCountsByStage(): BaseCollection
    {
        $ids = LessonProgress::query()
            ->where('user_id', auth()->id())
            ->whereIn('state', $this->readStateValues())
            ->pluck('lesson_id');

        return Lesson::query()
            ->published()
            ->whereIn('id', $ids)
            ->selectRaw('stage_id, COUNT(*) as read_count')
            ->groupBy('stage_id')
            ->pluck('read_count', 'stage_id')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * First published lesson the learner has not read yet, in stage order.
     */
    private function recommendation(): ?Lesson
    {
        $readIds = LessonProgress::query()
            ->where('user_id', auth()->id())
            ->whereIn('state', $this->readStateValues())
            ->pluck('lesson_id')
            ->flip();

        return Lesson::query()
            ->published()
            ->with('stage:id,number,ord')
            ->get()
            ->sortBy([['stage.number', 'asc'], ['ord', 'asc']])
            ->first(fn (Lesson $lesson) => ! $readIds->has($lesson->id));
    }

    /**
     * Builds the prerequisite graph as Mermaid `flowchart TD`, coloring nodes
     * by mastery level, highlighting the selection and the recommended
     * lesson's core concepts.
     *
     * @param  list<int>  $recommendedIds
     */
    private function buildMermaid(?int $selectedId, array $recommendedIds, GateEvidence $evidence): string
    {
        $edges = ConceptPrerequisite::query()
            ->with(['concept:id,slug,name', 'prereqConcept:id,slug,name'])
            ->get();

        /** @var array<int, array{slug: string, name: string}> $nodes */
        $nodes = [];
        /** @var list<array{from: int, to: int, weight: int}> $pairs */
        $pairs = [];

        foreach ($edges as $edge) {
            if ($edge->concept === null || $edge->prereqConcept === null) {
                continue;
            }

            $nodes[$edge->prereq_concept_id] = ['slug' => $edge->prereqConcept->slug, 'name' => $edge->prereqConcept->name];
            $nodes[$edge->concept_id] = ['slug' => $edge->concept->slug, 'name' => $edge->concept->name];
            $pairs[] = ['from' => $edge->prereq_concept_id, 'to' => $edge->concept_id, 'weight' => (int) $edge->weight];
        }

        if ($selectedId !== null && ! isset($nodes[$selectedId])) {
            $selected = Concept::query()->find($selectedId, ['id', 'slug', 'name']);

            if ($selected !== null) {
                $nodes[$selected->id] = ['slug' => $selected->slug, 'name' => $selected->name];
            }
        }

        if ($nodes === []) {
            return '';
        }

        $lines = ['flowchart TD'];

        foreach ($nodes as $id => $node) {
            $lines[] = '    n'.$id.'["'.$this->nodeLabel($node['name']).'"]';
        }

        foreach ($pairs as $pair) {
            $arrow = $pair['weight'] > 0 ? '-->' : '-.->';
            $lines[] = "    n{$pair['from']} {$arrow} n{$pair['to']}";
        }

        foreach ($nodes as $id => $node) {
            $level = $evidence->levelFor($node['slug']);
            $style = 'fill:'.$level->color().',stroke:#52525b';

            if ($id === $selectedId) {
                $style = 'fill:#e0f2fe,stroke:#0ea5e9,stroke-width:4px';
            } elseif (in_array($id, $recommendedIds, true)) {
                $style = 'fill:#e0f2fe,stroke:#0ea5e9,stroke-width:3px,stroke-dasharray: 5 5';
            }

            $lines[] = "    style n{$id} {$style}";
        }

        return implode("\n", $lines);
    }

    private function nodeLabel(string $name): string
    {
        return str_replace(['"', '[', ']'], '', $name);
    }
}
