<?php

namespace App\Enums;

enum AiMode: string
{
    case Tutor = 'tutor';
    case Teach = 'teach';
    case Quiz = 'quiz';
    case Exercise = 'exercise';

    public function label(): string
    {
        return match ($this) {
            self::Tutor => 'Tutor',
            self::Teach => 'Teach',
            self::Quiz => 'Quiz',
            self::Exercise => 'Exercise',
        };
    }
}
