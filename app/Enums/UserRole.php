<?php

namespace App\Enums;

enum UserRole: string
{
    case Learner = 'learner';
    case Admin = 'admin';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Learner',
            self::Admin => 'Admin',
        };
    }
}
