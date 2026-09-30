<?php

namespace App\Services\Learning;

use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads and grows the prerequisite graph (`concept_prerequisites`).
 *
 * Edge direction: row (concept_id = C, prereq_concept_id = P) means
 * "P must come before C", drawn in Mermaid as P --> C.
 *
 * Cycle prevention is the contract: an edge may never make a concept
 * (transitively) its own prerequisite.
 */
final class ConceptGraph
{
    /**
     * Concepts that must be learned before $concept (transitive),
     * nearest prerequisites first.
     *
     * @return Collection<int, Concept>
     */
    public function ancestors(Concept|int $concept): Collection
    {
        return $this->hydrate($this->walk($this->adjacency('prereqs'), $this->id($concept)));
    }

    /**
     * Concepts that (transitively) depend on $concept, nearest first.
     *
     * @return Collection<int, Concept>
     */
    public function descendants(Concept|int $concept): Collection
    {
        return $this->hydrate($this->walk($this->adjacency('dependents'), $this->id($concept)));
    }

    /**
     * True when adding edge concept_id => prereq_concept_id would create a loop:
     * either a self-edge, or the prereq already (transitively) depends on the concept.
     */
    public function wouldCreateCycle(int $conceptId, int $prereqId): bool
    {
        if ($conceptId === $prereqId) {
            return true;
        }

        return in_array($prereqId, $this->walk($this->adjacency('dependents'), $conceptId), true);
    }

    /**
     * Adds (or updates) a prerequisite edge, rejecting cycles.
     *
     * @throws CycleDetected
     */
    public function addPrerequisite(Concept|int $concept, Concept|int $prereq, int $weight = 1): void
    {
        $conceptId = $this->id($concept);
        $prereqId = $this->id($prereq);

        if ($this->wouldCreateCycle($conceptId, $prereqId)) {
            throw new CycleDetected(
                "Adding prerequisite [{$prereqId}] to [{$conceptId}] would create a cycle."
            );
        }

        ConceptPrerequisite::query()->updateOrCreate(
            ['concept_id' => $conceptId, 'prereq_concept_id' => $prereqId],
            ['weight' => $weight, 'source' => 'manual'],
        );
    }

    private function id(Concept|int $concept): int
    {
        return $concept instanceof Concept ? $concept->id : $concept;
    }

    /**
     * Adjacency map in edge direction: 'prereqs' maps a concept to what it
     * depends on, 'dependents' maps a concept to what depends on it.
     *
     * @return array<int, list<int>>
     */
    private function adjacency(string $direction): array
    {
        $map = [];

        foreach (ConceptPrerequisite::query()->get() as $edge) {
            if ($direction === 'prereqs') {
                $map[$edge->concept_id][] = $edge->prereq_concept_id;
            } else {
                $map[$edge->prereq_concept_id][] = $edge->concept_id;
            }
        }

        return $map;
    }

    /**
     * Breadth-first walk from $start, excluding $start itself, in discovery order.
     *
     * @param  array<int, list<int>>  $adjacency
     * @return list<int>
     */
    private function walk(array $adjacency, int $start): array
    {
        $seen = [$start => true];
        $queue = [$start];
        $order = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($adjacency[$current] ?? [] as $next) {
                if (isset($seen[$next])) {
                    continue;
                }

                $seen[$next] = true;
                $order[] = $next;
                $queue[] = $next;
            }
        }

        return $order;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Concept>
     */
    private function hydrate(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $byId = Concept::query()->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = [];

        foreach ($ids as $id) {
            $concept = $byId->get($id);

            if ($concept !== null) {
                $ordered[] = $concept;
            }
        }

        return new Collection($ordered);
    }
}
