<?php

namespace App\Services\Learning;

use App\Enums\AttemptResult;
use App\Enums\MasteryLevel;
use App\Enums\SkillDomain;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\ExerciseAttempt;
use App\Models\SkillProgress;
use App\Models\User;

/**
 * Computes per-domain skill levels from concept mastery counts and
 * exercise pass rate (docs/04 section 9, docs/11 section 6). Reading
 * percentage is never an input.
 *
 * masteryPct = (4*mastered + 3*comfortable + 2*practicing + 1*learning) / (4*concepts)
 * pass rate gates the top two levels when attempts exist.
 */
final class SkillAggregator
{
    private const THRESHOLD_COMFORTABLE = 0.6;

    private const THRESHOLD_MASTERED = 0.9;

    private const PASS_COMFORTABLE = 0.5;

    private const PASS_MASTERED = 0.7;

    /**
     * Recompute and persist every domain for this learner.
     *
     * @return list<array{
     *     domain: SkillDomain,
     *     level: MasteryLevel,
     *     concept_count: int,
     *     mastered_count: int,
     *     comfortable_count: int,
     *     pass_rate: float|null,
     *     concepts: list<array{id: int, slug: string, name: string, level: MasteryLevel, evidence: array<string, int>}>
     * }>
     */
    public function aggregate(User $user): array
    {
        $conceptsByDomain = [];

        foreach (SkillDomain::cases() as $domain) {
            $conceptsByDomain[$domain->value] = [];
        }

        foreach (Concept::query()->where('status', 'published')->get(['id', 'slug', 'name', 'skill_domain']) as $concept) {
            if (isset($conceptsByDomain[$concept->skill_domain])) {
                $conceptsByDomain[$concept->skill_domain][] = $concept;
            }
        }

        $mastery = ConceptMastery::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('concept_id');

        $passRates = $this->passRatesByDomain($user);

        $rows = [];

        foreach (SkillDomain::cases() as $domain) {
            $concepts = $conceptsByDomain[$domain->value];
            $conceptIds = array_map(fn (Concept $concept): int => $concept->id, $concepts);

            $counts = array_fill_keys(array_map(fn (MasteryLevel $level): string => $level->value, MasteryLevel::cases()), 0);
            $displayConcepts = [];

            foreach ($concepts as $concept) {
                $row = $mastery->get($concept->id);
                $level = $row === null ? MasteryLevel::Unseen : $row->level;
                $counts[$level->value]++;

                $displayConcepts[] = [
                    'id' => $concept->id,
                    'slug' => $concept->slug,
                    'name' => $concept->name,
                    'level' => $level,
                    'evidence' => $row === null ? [] : $row->evidence,
                ];
            }

            $total = count($concepts);
            $weighted = 4 * $counts[MasteryLevel::Mastered->value]
                + 3 * $counts[MasteryLevel::Comfortable->value]
                + 2 * $counts[MasteryLevel::Practicing->value]
                + 1 * $counts[MasteryLevel::Learning->value];
            $masteryPct = $total > 0 ? $weighted / (4 * $total) : 0.0;

            $passRate = $passRates[$domain->value] ?? null;
            $pass = $passRate ?? 1.0;

            $level = match (true) {
                $total === 0 => MasteryLevel::Unseen,
                $masteryPct >= self::THRESHOLD_MASTERED && $pass >= self::PASS_MASTERED => MasteryLevel::Mastered,
                $masteryPct >= self::THRESHOLD_COMFORTABLE && $pass >= self::PASS_COMFORTABLE => MasteryLevel::Comfortable,
                $masteryPct >= 0.3 => MasteryLevel::Practicing,
                $masteryPct > 0 => MasteryLevel::Learning,
                default => MasteryLevel::Unseen,
            };

            SkillProgress::query()->updateOrCreate(
                ['user_id' => $user->id, 'skill_domain' => $domain->value],
                [
                    'level' => $level,
                    'mastered_count' => $counts[MasteryLevel::Mastered->value],
                    'comfortable_count' => $counts[MasteryLevel::Comfortable->value],
                ],
            );

            $rows[] = [
                'domain' => $domain,
                'level' => $level,
                'concept_count' => $total,
                'mastered_count' => $counts[MasteryLevel::Mastered->value],
                'comfortable_count' => $counts[MasteryLevel::Comfortable->value],
                'pass_rate' => $passRate,
                'concepts' => $displayConcepts,
            ];
        }

        return $rows;
    }

    /**
     * Exercise pass rate per skill domain (null = no attempts yet).
     *
     * @return array<string, float|null>
     */
    private function passRatesByDomain(User $user): array
    {
        $domainByConceptId = Concept::query()
            ->where('status', 'published')
            ->pluck('skill_domain', 'id');

        $attempts = ExerciseAttempt::query()
            ->where('user_id', $user->id)
            ->with('exercise:id,concept_id')
            ->get(['exercise_id', 'result']);

        /** @var array<string, array{correct: int, total: int}> $buckets */
        $buckets = [];

        foreach ($attempts as $attempt) {
            $conceptId = $attempt->exercise?->concept_id;

            if ($conceptId === null) {
                continue;
            }

            $domain = $domainByConceptId[$conceptId] ?? null;

            if ($domain === null) {
                continue;
            }

            $bucket = $buckets[$domain] ?? ['correct' => 0, 'total' => 0];
            $bucket['total']++;

            if ($attempt->result === AttemptResult::Correct) {
                $bucket['correct']++;
            }

            $buckets[$domain] = $bucket;
        }

        $rates = [];

        foreach ($buckets as $domain => $stats) {
            $rates[$domain] = $stats['total'] > 0 ? (float) ($stats['correct'] / $stats['total']) : null;
        }

        return $rates;
    }
}
