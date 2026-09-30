<?php

namespace App\Services\Learning;

final class QuizScorer
{
    public const WEAK_THRESHOLD = 60.0;

    /**
     * @param  list<array{correct: bool, concept_slug?: string|null}>  $answers
     */
    public function score(array $answers): QuizResult
    {
        $total = count($answers);
        $correct = count(array_filter($answers, fn (array $a): bool => $a['correct']));

        $score = $total === 0 ? 0.0 : round($correct / $total * 100, 2);

        return new QuizResult($score, $correct, $total, $this->weakConcepts($answers, $score));
    }

    /**
     * Concepts the learner scored below the weak threshold on (docs/04 §6).
     *
     * @param  list<array{correct: bool, concept_slug?: string|null}>  $answers
     * @return list<string>
     */
    private function weakConcepts(array $answers, float $overall): array
    {
        /** @var array<string, array{correct: int, total: int}> $byConcept */
        $byConcept = [];

        foreach ($answers as $answer) {
            $slug = $answer['concept_slug'] ?? null;
            if ($slug === null || $slug === '') {
                continue;
            }

            $byConcept[$slug]['total'] = ($byConcept[$slug]['total'] ?? 0) + 1;
            if ($answer['correct']) {
                $byConcept[$slug]['correct'] = ($byConcept[$slug]['correct'] ?? 0) + 1;
            }
        }

        $weak = [];
        foreach ($byConcept as $slug => $stats) {
            $pct = $stats['total'] === 0 ? 0.0 : $stats['correct'] / $stats['total'] * 100;
            if ($pct < self::WEAK_THRESHOLD) {
                $weak[] = $slug;
            }
        }

        sort($weak);

        return $weak;
    }
}
