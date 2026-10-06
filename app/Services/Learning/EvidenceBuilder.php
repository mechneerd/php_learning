<?php

namespace App\Services\Learning;

use App\Enums\MasteryLevel;
use App\Models\Concept;
use App\Models\ConceptMastery;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\QuizAttempt;

/**
 * Fills GateEvidence for a learner from the Phase 5 tables so gate checks
 * on /path and /dashboard report live pass/fail instead of empty evidence.
 */
final class EvidenceBuilder
{
    public function forUser(int $userId): GateEvidence
    {
        $levels = [];

        $slugById = Concept::query()->pluck('slug', 'id');

        foreach (ConceptMastery::query()->where('user_id', $userId)->get() as $row) {
            $slug = $slugById[$row->concept_id] ?? null;

            if ($slug !== null) {
                $levels[(string) $slug] = $row->level;
            }
        }

        $totalConcepts = Concept::query()->count();
        $comfortable = 0;
        $mastered = 0;

        foreach ($levels as $level) {
            if ($level->rank() >= MasteryLevel::Comfortable->rank()) {
                $comfortable++;
            }

            if ($level === MasteryLevel::Mastered) {
                $mastered++;
            }
        }

        $quizScores = [];
        $lessonSlugs = Lesson::query()->pluck('slug', 'id');

        $bestQuiz = QuizAttempt::query()
            ->where('user_id', $userId)
            ->orderByDesc('score')
            ->get(['lesson_id', 'score']);

        foreach ($bestQuiz as $attempt) {
            $slug = $lessonSlugs[$attempt->lesson_id] ?? null;

            if ($slug === null) {
                continue;
            }

            $quizScores[(string) $slug] = max(
                $quizScores[(string) $slug] ?? 0,
                (int) round((float) $attempt->score),
            );
        }

        $correctExercises = ExerciseAttempt::query()
            ->where('user_id', $userId)
            ->where('result', 'correct')
            ->distinct()
            ->count('exercise_id');

        return new GateEvidence(
            levels: $levels,
            quizScores: $quizScores,
            correctExercises: $correctExercises,
            comfortablePct: $totalConcepts > 0 ? (int) round($comfortable * 100 / $totalConcepts) : 0,
            masteredPct: $totalConcepts > 0 ? (int) round($mastered * 100 / $totalConcepts) : 0,
        );
    }
}
