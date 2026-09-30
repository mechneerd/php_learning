<?php

namespace App\Enums;

/**
 * Concept mastery ladder: unseen → learning → practicing → comfortable → mastered.
 * Stored per user+concept (Phase 5 `concept_mastery`); nodes on the learning
 * path are colored from this ladder.
 */
enum MasteryLevel: string
{
    case Unseen = 'unseen';
    case Learning = 'learning';
    case Practicing = 'practicing';
    case Comfortable = 'comfortable';
    case Mastered = 'mastered';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Total order used for gate checks ("practicing or better") and
     * to avoid downgrading a learner.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unseen => 0,
            self::Learning => 1,
            self::Practicing => 2,
            self::Comfortable => 3,
            self::Mastered => 4,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Mermaid `style` fill color for graph nodes.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unseen => '#d4d4d8',
            self::Learning => '#bfdbfe',
            self::Practicing => '#fde68a',
            self::Comfortable => '#bbf7d0',
            self::Mastered => '#4ade80',
        };
    }
}
