<?php

namespace App\Services\Ai\Drafts;

/**
 * Base for every Pipeline A DTO. Concrete drafts declare their JSON
 * schema (embedded in the prompt) and their parser; ::parses() is the
 * retry predicate used by GenerationRunner.
 */
abstract class Draft
{
    /**
     * JSON-schema-style description shown to the model in the prompt.
     *
     * @return array<string, mixed>
     */
    abstract public static function schema(): array;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidDraft
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Retry predicate: decodes and parses without throwing.
     */
    public static function parses(string $json): bool
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return false;
        }

        try {
            static::fromArray($data);
        } catch (InvalidDraft) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function str(array $data, string $task, string ...$path): string
    {
        $value = $path === [] ? $data : self::dig($data, $path);

        if (! is_string($value) || trim($value) === '') {
            throw InvalidDraft::missing($task, implode('.', $path));
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected static function rows(array $data, string $task, string ...$path): array
    {
        $value = self::dig($data, $path);

        if (! is_array($value) || $value === []) {
            throw InvalidDraft::emptyList($task, implode('.', $path));
        }

        $rows = [];

        foreach (array_values($value) as $i => $row) {
            if (! is_array($row)) {
                throw InvalidDraft::wrongType($task, implode('.', $path)."[{$i}]", 'an object');
            }

            /** @var array<string, mixed> $row */
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, string>  $path
     */
    private static function dig(array $data, array $path): mixed
    {
        $value = $data;

        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }
}
