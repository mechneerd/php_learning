<?php

namespace App\Enums;

enum QuizQuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case Output = 'output';
    case Completion = 'completion';
    case Debug = 'debug';
    case ShortAnswer = 'short_answer';

    public function label(): string
    {
        return match ($this) {
            self::Mcq => 'Multiple choice',
            self::TrueFalse => 'True / False',
            self::Output => 'Predict the output',
            self::Completion => 'Fill in the blank',
            self::Debug => 'Spot the bug',
            self::ShortAnswer => 'Short answer',
        };
    }

    public function usesOptions(): bool
    {
        return match ($this) {
            self::Mcq, self::TrueFalse, self::Output, self::Completion, self::Debug => true,
            self::ShortAnswer => false,
        };
    }
}
