<?php

namespace App\Enums;

enum ReviewItemStatus: string
{
    case Due = 'due';
    case Done = 'done';
    case Snoozed = 'snoozed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
