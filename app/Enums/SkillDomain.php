<?php

namespace App\Enums;

/**
 * The 16 skill domains (docs/04 section 9). Every concept.skill_domain
 * value maps to exactly one of these cases.
 */
enum SkillDomain: string
{
    case PhpSyntax = 'php_syntax';
    case Variables = 'variables';
    case DataTypes = 'data_types';
    case Conditions = 'conditions';
    case Loops = 'loops';
    case Functions = 'functions';
    case Arrays = 'arrays';
    case Strings = 'strings';
    case Files = 'files';
    case Http = 'http';
    case Oop = 'oop';
    case Exceptions = 'exceptions';
    case Database = 'database';
    case Security = 'security';
    case Testing = 'testing';
    case ModernPhp = 'modern_php';

    public function label(): string
    {
        return match ($this) {
            self::PhpSyntax => 'PHP syntax',
            self::ModernPhp => 'Modern PHP',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
