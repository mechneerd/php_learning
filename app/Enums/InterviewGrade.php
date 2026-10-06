<?php

namespace App\Enums;

enum InterviewGrade: string
{
    case Confident = 'confident';
    case Shaky = 'shaky';
    case Missed = 'missed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Days until the self-graded question is due again (spaced review).
     */
    public function dueDelayDays(): int
    {
        return match ($this) {
            self::Confident => 14,
            self::Shaky => 3,
            self::Missed => 0,
        };
    }

    public function reason(): string
    {
        return 'interview: '.$this->value;
    }
}
