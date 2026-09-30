<?php

namespace App\Enums;

/**
 * Review grade for a spaced-repetition flashcard review
 * (named per the Phase 4 plan's enum list: ExerciseType, ExerciseDifficulty,
 * AttemptResult, QuizQuestionType, CardType, HintGrade).
 */
enum HintGrade: string
{
    case Hard = 'hard';
    case Ok = 'ok';
    case Easy = 'easy';

    public function label(): string
    {
        return match ($this) {
            self::Hard => 'Hard',
            self::Ok => 'Got it',
            self::Easy => 'Easy',
        };
    }
}
