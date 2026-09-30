<?php

namespace App\Enums;

enum AttemptResult: string
{
    case Correct = 'correct';
    case Incorrect = 'incorrect';
    case Partial = 'partial';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Correct => 'Passed',
            self::Incorrect => 'Failed',
            self::Partial => 'Partially correct',
            self::Error => 'Error',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Correct => 'bg-emerald-500/15 text-emerald-400 ring-emerald-500/30',
            self::Incorrect => 'bg-rose-500/15 text-rose-400 ring-rose-500/30',
            self::Partial => 'bg-amber-500/15 text-amber-400 ring-amber-500/30',
            self::Error => 'bg-zinc-500/15 text-zinc-400 ring-zinc-500/30',
        };
    }
}
