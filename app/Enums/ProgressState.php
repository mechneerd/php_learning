<?php

namespace App\Enums;

enum ProgressState: string
{
    case Opened = 'opened';
    case Read = 'read';
    case Explained = 'explained';
    case Practiced = 'practiced';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Rank used to avoid downgrading a learner's state.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Opened => 0,
            self::Read => 1,
            self::Explained => 2,
            self::Practiced => 3,
            self::Reviewed => 4,
        };
    }
}
