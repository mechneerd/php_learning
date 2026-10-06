<?php

namespace App\Services\Ai\Drafts;

/**
 * DetectOutdatedJob output: whether a lesson has aged out of the book's
 * era plus replacement BOOK / MODERN / WHY panel copy.
 */
final class OutdatedDraft extends Draft
{
    public function __construct(
        public readonly bool $isOutdated,
        public readonly string $book,
        public readonly string $modern,
        public readonly string $why,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['is_outdated', 'book', 'modern', 'why'],
            'properties' => [
                'is_outdated' => ['type' => 'boolean'],
                'book' => ['type' => 'string'],
                'modern' => ['type' => 'string'],
                'why' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $flag = $data['is_outdated'] ?? null;

        if (is_string($flag)) {
            $flag = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (! is_bool($flag)) {
            throw InvalidDraft::wrongType('outdated', 'is_outdated', 'a boolean');
        }

        return new self(
            $flag,
            self::str($data, 'outdated', 'book'),
            self::str($data, 'outdated', 'modern'),
            self::str($data, 'outdated', 'why'),
        );
    }
}
