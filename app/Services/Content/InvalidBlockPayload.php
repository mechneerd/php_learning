<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use RuntimeException;

class InvalidBlockPayload extends RuntimeException
{
    public static function missing(BlockType $type, string $field): self
    {
        return new self("Block [{$type->value}] requires payload field [{$field}].");
    }

    public static function wrongType(BlockType $type, string $field, string $expected): self
    {
        return new self("Block [{$type->value}] payload field [{$field}] must be {$expected}.");
    }
}
