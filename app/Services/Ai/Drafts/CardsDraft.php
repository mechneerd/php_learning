<?php

namespace App\Services\Ai\Drafts;

use App\Enums\CardType;

/**
 * Flashcards for one lesson.
 */
final class CardsDraft extends Draft
{
    /**
     * @param  list<array{front: string, back: string, card_type: CardType}>  $cards
     */
    public function __construct(public readonly array $cards) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $item = [
            'type' => 'object',
            'required' => ['front', 'back'],
            'properties' => [
                'front' => ['type' => 'string'],
                'back' => ['type' => 'string'],
                'card_type' => ['type' => 'string', 'enum' => array_column(CardType::cases(), 'value')],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['cards'],
            'properties' => ['cards' => ['type' => 'array', 'items' => $item, 'minItems' => 2, 'maxItems' => 8]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $cards = [];

        foreach (self::rows($data, 'cards', 'cards') as $i => $row) {
            $type = CardType::tryFrom((string) ($row['card_type'] ?? '')) ?? CardType::Definition;

            $cards[] = [
                'front' => self::str($row, 'cards', 'front'),
                'back' => self::str($row, 'cards', 'back'),
                'card_type' => $type,
            ];
        }

        return new self($cards);
    }
}
