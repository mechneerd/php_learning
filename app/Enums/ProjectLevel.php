<?php

namespace App\Enums;

enum ProjectLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Capstone = 'capstone';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Beginner => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
            self::Intermediate => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            self::Advanced => 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
            self::Capstone => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
        };
    }
}
