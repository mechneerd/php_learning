<?php

namespace App\Enums;

enum ProvenanceSource: string
{
    case Book = 'book';
    case Ai = 'ai';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Book',
            self::Ai => 'AI-generated',
            self::Hybrid => 'Book + AI',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Book => 'book',
            self::Ai => 'ai',
            self::Hybrid => 'hybrid',
        };
    }
}
