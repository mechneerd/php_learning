<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = ucfirst(str_replace('_', ' ', $case->value));
        }

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
