<?php

namespace App\Enums;

/**
 * How granular a concept entry is (docs/06-database-design.md Module D).
 */
enum ConceptGranularity: string
{
    case Topic = 'topic';
    case Concept = 'concept';
    case Detail = 'detail';

    public function label(): string
    {
        return match ($this) {
            self::Topic => 'Topic',
            self::Concept => 'Concept',
            self::Detail => 'Detail',
        };
    }
}
