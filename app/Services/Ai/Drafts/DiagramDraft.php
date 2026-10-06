<?php

namespace App\Services\Ai\Drafts;

use App\Enums\DiagramKind;

/**
 * A Mermaid diagram for a lesson. Renderability is checked by
 * MermaidSanitizer when the job persists it.
 */
final class DiagramDraft extends Draft
{
    public function __construct(
        public readonly string $title,
        public readonly DiagramKind $kind,
        public readonly string $mermaidSource,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'kind', 'mermaid_source'],
            'properties' => [
                'title' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => array_column(DiagramKind::cases(), 'value')],
                'mermaid_source' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $kind = DiagramKind::tryFrom((string) ($data['kind'] ?? ''));

        if ($kind === null) {
            throw InvalidDraft::wrongType('diagram', 'kind', 'a known diagram kind');
        }

        return new self(
            self::str($data, 'diagram', 'title'),
            $kind,
            self::str($data, 'diagram', 'mermaid_source'),
        );
    }
}
