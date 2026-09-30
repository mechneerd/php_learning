<?php

namespace App\Enums;

enum ExerciseType: string
{
    case Write = 'write';
    case Complete = 'complete';
    case PredictOutput = 'predict_output';
    case FindError = 'find_error';
    case Fix = 'fix';
    case Mcq = 'mcq';
    case Explain = 'explain';
    case Problem = 'problem';

    public function label(): string
    {
        return match ($this) {
            self::Write => 'Write code',
            self::Complete => 'Complete the code',
            self::PredictOutput => 'Predict the output',
            self::FindError => 'Find the error',
            self::Fix => 'Fix the bug',
            self::Mcq => 'Multiple choice',
            self::Explain => 'Explain',
            self::Problem => 'Solve the problem',
        };
    }

    public function isCode(): bool
    {
        return match ($this) {
            self::Write, self::Complete, self::Fix, self::Problem => true,
            default => false,
        };
    }

    public function usesStoredAnswer(): bool
    {
        return match ($this) {
            self::Mcq, self::PredictOutput, self::FindError, self::Explain => true,
            default => false,
        };
    }
}
