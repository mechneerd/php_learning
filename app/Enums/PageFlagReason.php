<?php

namespace App\Enums;

enum PageFlagReason: string
{
    case LowText = 'low_text';
    case NoPageNumber = 'no_page_number';
    case HeadingMismatch = 'heading_mismatch';
    case ImageOnly = 'image_only';

    public function label(): string
    {
        return match ($this) {
            self::LowText => 'Too little extracted text',
            self::NoPageNumber => 'Printed page number not detected',
            self::HeadingMismatch => 'Heading not found on expected page',
            self::ImageOnly => 'Page appears to be image-only',
        };
    }
}
