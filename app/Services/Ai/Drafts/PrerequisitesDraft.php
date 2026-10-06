<?php

namespace App\Services\Ai\Drafts;

/**
 * Prerequisite edges between concept slugs. Cycles are rejected when the
 * job writes them (DerivePrerequisitesJob), not here.
 */
final class PrerequisitesDraft extends Draft
{
    /**
     * @param  list<array{slug: string, prereq: string}>  $edges
     */
    public function __construct(public readonly array $edges) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $edge = [
            'type' => 'object',
            'required' => ['slug', 'prereq'],
            'properties' => [
                'slug' => ['type' => 'string'],
                'prereq' => ['type' => 'string'],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['edges'],
            'properties' => ['edges' => ['type' => 'array', 'items' => $edge, 'maxItems' => 200]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $edges = [];
        $seen = [];

        $raw = $data['edges'] ?? [];

        if (! is_array($raw)) {
            throw InvalidDraft::wrongType('prerequisites', 'edges', 'an array');
        }

        foreach (array_values($raw) as $i => $row) {
            if (! is_array($row)) {
                throw InvalidDraft::wrongType('prerequisites', "edges[{$i}]", 'an object');
            }

            $slug = self::str($row, 'prerequisites', 'slug');
            $prereq = self::str($row, 'prerequisites', 'prereq');

            if ($slug === $prereq || isset($seen["{$slug}>{$prereq}"])) {
                continue;
            }

            $seen["{$slug}>{$prereq}"] = true;
            $edges[] = ['slug' => $slug, 'prereq' => $prereq];
        }

        return new self($edges);
    }
}
