<?php

namespace App\Services\Ai\Drafts;

use App\Enums\ConceptGranularity;

/**
 * Concepts extracted or generated for a chapter/stage, later linked to
 * lessons and wired into the prerequisite graph.
 */
final class ConceptsDraft extends Draft
{
    /**
     * @param  list<array{slug: string, name: string, definition: string, skill_domain: string, granularity: ConceptGranularity}>  $concepts
     */
    public function __construct(public readonly array $concepts) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $item = [
            'type' => 'object',
            'required' => ['slug', 'name', 'definition', 'skill_domain'],
            'properties' => [
                'slug' => ['type' => 'string', 'pattern' => '^[a-z0-9][a-z0-9-]*$'],
                'name' => ['type' => 'string'],
                'definition' => ['type' => 'string'],
                'skill_domain' => ['type' => 'string'],
                'granularity' => ['type' => 'string', 'enum' => array_column(ConceptGranularity::cases(), 'value')],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['concepts'],
            'properties' => ['concepts' => ['type' => 'array', 'items' => $item, 'minItems' => 1, 'maxItems' => 20]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $concepts = [];

        foreach (self::rows($data, 'concepts', 'concepts') as $i => $row) {
            $slug = self::str($row, 'concepts', 'slug');

            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
                throw InvalidDraft::wrongType('concepts', "concepts[{$i}].slug", 'a lowercase kebab-case slug');
            }

            $concepts[] = [
                'slug' => $slug,
                'name' => self::str($row, 'concepts', 'name'),
                'definition' => self::str($row, 'concepts', 'definition'),
                'skill_domain' => self::str($row, 'concepts', 'skill_domain'),
                'granularity' => ConceptGranularity::tryFrom((string) ($row['granularity'] ?? ''))
                    ?? ConceptGranularity::Concept,
            ];
        }

        return new self($concepts);
    }
}
