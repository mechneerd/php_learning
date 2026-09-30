<?php

namespace App\Enums;

enum CardType: string
{
    case Definition = 'definition';
    case Syntax = 'syntax';
    case Difference = 'difference';
    case Snippet = 'snippet';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
