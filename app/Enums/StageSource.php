<?php

namespace App\Enums;

enum StageSource: string
{
    case Book = 'book';
    case Ai = 'ai';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'From the book',
            self::Ai => 'AI foundation (not in book)',
            self::Mixed => 'Book + AI scaffolding',
        };
    }
}
