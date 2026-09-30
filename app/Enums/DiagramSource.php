<?php

namespace App\Enums;

enum DiagramSource: string
{
    case BookFigure = 'book_figure';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::BookFigure => 'Book figure',
            self::Ai => 'AI',
        };
    }
}
