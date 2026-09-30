<?php

namespace App\Enums;

enum BookStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
