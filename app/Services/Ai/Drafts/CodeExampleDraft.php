<?php

namespace App\Services\Ai\Drafts;

use App\Enums\CodeTier;

/**
 * Book-derived code examples for a lesson, tiered for progressive
 * disclosure (tier 3+ hide behind "Show real-world example").
 */
final class CodeExampleDraft extends Draft
{
    /**
     * @param  list<array{title: string, tier: CodeTier, code: string, expected_output: string|null, explanation: string}>  $examples
     */
    public function __construct(public readonly array $examples) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $item = [
            'type' => 'object',
            'required' => ['title', 'tier', 'code', 'explanation'],
            'properties' => [
                'title' => ['type' => 'string'],
                'tier' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4],
                'code' => ['type' => 'string'],
                'expected_output' => ['type' => ['string', 'null']],
                'explanation' => ['type' => 'string'],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['examples'],
            'properties' => ['examples' => ['type' => 'array', 'items' => $item, 'minItems' => 1, 'maxItems' => 4]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $examples = [];

        foreach (self::rows($data, 'code_examples', 'examples') as $i => $row) {
            $tier = $row['tier'] ?? null;

            if (is_string($tier) && ctype_digit($tier)) {
                $tier = (int) $tier;
            }

            $tier = is_int($tier) ? CodeTier::tryFrom($tier) : null;

            if ($tier === null) {
                throw InvalidDraft::wrongType('code_examples', "examples[{$i}].tier", 'an integer 1-4');
            }

            $output = $row['expected_output'] ?? null;

            $examples[] = [
                'title' => self::str($row, 'code_examples', 'title'),
                'tier' => $tier,
                'code' => self::str($row, 'code_examples', 'code'),
                'expected_output' => is_string($output) && trim($output) !== '' ? trim($output) : null,
                'explanation' => self::str($row, 'code_examples', 'explanation'),
            ];
        }

        return new self($examples);
    }
}
