<?php

namespace App\Services\Ai\Drafts;

use RuntimeException;

/**
 * Raised when a model reply cannot be parsed into a generation DTO.
 * The GenerationRunner catches this path through Draft::parses() and
 * retries once with a correction prompt before failing the job (docs/10).
 */
final class InvalidDraft extends RuntimeException
{
    public static function missing(string $task, string $field): self
    {
        return new self("{$task} draft is missing field '{$field}'.");
    }

    public static function wrongType(string $task, string $field, string $expected): self
    {
        return new self("{$task} draft field '{$field}' must be {$expected}.");
    }

    public static function emptyList(string $task, string $field): self
    {
        return new self("{$task} draft list '{$field}' must not be empty.");
    }
}
