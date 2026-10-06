<?php

namespace App\Jobs;

use App\Models\Concept;
use App\Services\Ai\Generators\PrerequisitesGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline A: the concept set -> cycle-checked prerequisite edges
 * (docs/10 DerivePrerequisitesJob).
 */
class DerivePrerequisitesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(PrerequisitesGenerator $generator): void
    {
        $concepts = Concept::query()->orderBy('slug')->get(['id', 'slug']);

        if ($concepts->isEmpty()) {
            return;
        }

        $bySlug = $concepts->keyBy('slug');

        $slugs = [];
        foreach ($concepts as $concept) {
            $slugs[] = $concept->slug;
        }

        $draft = $generator->generate($slugs);

        if ($draft === null) {
            return;
        }

        foreach ($draft->edges as $edge) {
            $concept = $bySlug->get($edge['slug']);
            $prereq = $bySlug->get($edge['prereq']);

            if ($concept === null || $prereq === null || $concept->id === $prereq->id) {
                continue;
            }

            if ($this->wouldCycle($concept->id, $prereq->id)) {
                continue;
            }

            DB::table('concept_prerequisites')->updateOrInsert(
                ['concept_id' => $concept->id, 'prereq_concept_id' => $prereq->id],
                ['weight' => 1, 'source' => 'ai', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * True when making $prereqId a prerequisite of $conceptId would close a
     * loop (i.e. $conceptId is already reachable from $prereqId).
     */
    private function wouldCycle(int $conceptId, int $prereqId): bool
    {
        $stack = [$prereqId];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $conceptId) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            $next = DB::table('concept_prerequisites')
                ->where('concept_id', $current)
                ->pluck('prereq_concept_id');

            foreach ($next as $id) {
                $stack[] = (int) $id;
            }
        }

        return false;
    }
}
