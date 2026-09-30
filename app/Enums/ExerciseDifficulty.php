<?php

namespace App\Enums;

enum ExerciseDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
