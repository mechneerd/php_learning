<?php

namespace App\Enums;

/**
 * Why a concept is attached to a lesson (docs/06-database-design.md lesson_concepts.role).
 */
enum LessonConceptRole: string
{
    case Core = 'core';
    case Prereq = 'prereq';
    case BuiltOn = 'built_on';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Prereq => 'Prerequisite',
            self::BuiltOn => 'Built on',
        };
    }
}
