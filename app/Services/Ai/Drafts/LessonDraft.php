<?php

namespace App\Services\Ai\Drafts;

use App\Enums\BlockType;

/**
 * A generated lesson: metadata plus ordered blocks. Block payloads are
 * schema-checked again by BlockValidator when the job persists them.
 */
final class LessonDraft extends Draft
{
    /**
     * @param  list<array{type: string, payload: array<string, mixed>}>  $blocks
     */
    public function __construct(
        public readonly string $title,
        public readonly string $summary,
        public readonly int $estMinutes,
        public readonly array $blocks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'summary', 'est_minutes', 'blocks'],
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'est_minutes' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 30],
                'blocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['type', 'payload'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => array_column(BlockType::cases(), 'value')],
                            'payload' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $blocks = [];

        foreach (self::rows($data, 'lesson', 'blocks') as $i => $row) {
            $type = $row['type'] ?? null;
            $payload = $row['payload'] ?? null;

            if (! is_string($type) || BlockType::tryFrom($type) === null) {
                throw InvalidDraft::wrongType('lesson', "blocks[{$i}].type", 'a known block type');
            }

            if (! is_array($payload)) {
                throw InvalidDraft::wrongType('lesson', "blocks[{$i}].payload", 'an object');
            }

            /** @var array<string, mixed> $payload */
            $blocks[] = ['type' => $type, 'payload' => $payload];
        }

        $est = $data['est_minutes'] ?? null;

        if (is_string($est) && ctype_digit($est)) {
            $est = (int) $est;
        }

        if (! is_int($est) || $est < 1) {
            throw InvalidDraft::wrongType('lesson', 'est_minutes', 'a positive integer');
        }

        return new self(
            self::str($data, 'lesson', 'title'),
            self::str($data, 'lesson', 'summary'),
            $est,
            $blocks,
        );
    }
}
