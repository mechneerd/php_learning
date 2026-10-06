<?php

namespace App\Services\Ai\Drafts;

use App\Enums\ExerciseDifficulty;
use App\Enums\ExerciseType;

/**
 * Practice items for one lesson: prompt, grading shape, hint ladder and
 * the expected answer used by static grading.
 */
final class ExerciseDraft extends Draft
{
    /**
     * @param  list<array{prompt: string, type: ExerciseType, difficulty: ExerciseDifficulty, hints: list<string>, answer: string|list<string>}>  $exercises
     */
    public function __construct(public readonly array $exercises) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $item = [
            'type' => 'object',
            'required' => ['prompt', 'type', 'difficulty', 'hints', 'answer'],
            'properties' => [
                'prompt' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => array_column(ExerciseType::cases(), 'value')],
                'difficulty' => ['type' => 'string', 'enum' => array_column(ExerciseDifficulty::cases(), 'value')],
                'hints' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 3],
                'answer' => ['type' => ['string', 'array']],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['exercises'],
            'properties' => ['exercises' => ['type' => 'array', 'items' => $item, 'minItems' => 1, 'maxItems' => 4]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $exercises = [];

        foreach (self::rows($data, 'exercise', 'exercises') as $i => $row) {
            $type = ExerciseType::tryFrom((string) ($row['type'] ?? ''));
            $difficulty = ExerciseDifficulty::tryFrom((string) ($row['difficulty'] ?? ''));

            if ($type === null) {
                throw InvalidDraft::wrongType('exercise', "exercises[{$i}].type", 'a known exercise type');
            }

            if ($difficulty === null) {
                throw InvalidDraft::wrongType('exercise', "exercises[{$i}].difficulty", 'easy, medium or hard');
            }

            $hints = [];

            foreach ((array) ($row['hints'] ?? []) as $hint) {
                if (is_string($hint) && trim($hint) !== '') {
                    $hints[] = trim($hint);
                }
            }

            if ($hints === []) {
                throw InvalidDraft::emptyList('exercise', "exercises[{$i}].hints");
            }

            $answer = $row['answer'] ?? null;

            if (is_string($answer) && trim($answer) !== '') {
                $answer = trim($answer);
            } elseif (is_array($answer)) {
                $answer = array_values(array_filter(array_map(
                    static fn (mixed $a): string => is_scalar($a) ? trim((string) $a) : '',
                    $answer,
                ), static fn (string $a): bool => $a !== ''));

                if ($answer === []) {
                    throw InvalidDraft::emptyList('exercise', "exercises[{$i}].answer");
                }
            } else {
                throw InvalidDraft::missing('exercise', "exercises[{$i}].answer");
            }

            $exercises[] = [
                'prompt' => self::str($row, 'exercise', 'prompt'),
                'type' => $type,
                'difficulty' => $difficulty,
                'hints' => $hints,
                'answer' => $answer,
            ];
        }

        return new self($exercises);
    }
}
