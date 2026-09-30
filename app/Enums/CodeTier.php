<?php

namespace App\Enums;

enum CodeTier: int
{
    case Starter = 1;
    case Core = 2;
    case RealWorld = 3;
    case Advanced = 4;

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Core => 'Core',
            self::RealWorld => 'Real-world',
            self::Advanced => 'Advanced',
        };
    }

    /**
     * Tiers 3+ hide behind "Show real-world example" (progressive disclosure).
     */
    public function isHiddenByDefault(): bool
    {
        return $this->value >= 3;
    }
}
