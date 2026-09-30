<?php

namespace App\Enums;

enum ImportJobType: string
{
    case Extract = 'extract';
    case DetectStructure = 'detect_chapters';
    case Generate = 'generate';
    case Publish = 'publish';

    public function label(): string
    {
        return match ($this) {
            self::Extract => 'Extract text',
            self::DetectStructure => 'Detect chapters & sections',
            self::Generate => 'Generate content',
            self::Publish => 'Publish',
        };
    }
}
