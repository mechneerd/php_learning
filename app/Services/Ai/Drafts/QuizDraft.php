<?php

namespace App\Services\Ai\Drafts;

use App\Enums\QuizQuestionType;

/**
 * Quiz questions for one lesson. Correctness is the zero-based index of
 * the correct option (only meaningful for types that use options).
 */
final class QuizDraft extends Draft
{
    /**
     * @param  list<array{question: string, type: QuizQuestionType, options: list<string>, correct: int, explanation: string}>  $questions
     */
    public function __construct(public readonly array $questions) {}

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $item = [
            'type' => 'object',
            'required' => ['question', 'type', 'options', 'correct', 'explanation'],
            'properties' => [
                'question' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => array_column(QuizQuestionType::cases(), 'value')],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'correct' => ['type' => 'integer', 'minimum' => 0],
                'explanation' => ['type' => 'string'],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['questions'],
            'properties' => ['questions' => ['type' => 'array', 'items' => $item, 'minItems' => 1, 'maxItems' => 6]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $questions = [];

        foreach (self::rows($data, 'quiz', 'questions') as $i => $row) {
            $type = QuizQuestionType::tryFrom((string) ($row['type'] ?? ''));

            if ($type === null) {
                throw InvalidDraft::wrongType('quiz', "questions[{$i}].type", 'a known question type');
            }

            $options = [];

            foreach ((array) ($row['options'] ?? []) as $option) {
                if (is_string($option) && trim($option) !== '') {
                    $options[] = trim($option);
                }
            }

            if ($type->usesOptions() && count($options) < 2) {
                throw InvalidDraft::wrongType('quiz', "questions[{$i}].options", 'at least two options');
            }

            $correct = $row['correct'] ?? null;

            if (is_string($correct) && ctype_digit($correct)) {
                $correct = (int) $correct;
            }

            if (! is_int($correct) || $correct < 0 || ($options !== [] && $correct >= count($options))) {
                throw InvalidDraft::wrongType('quiz', "questions[{$i}].correct", 'the index of the right option');
            }

            $questions[] = [
                'question' => self::str($row, 'quiz', 'question'),
                'type' => $type,
                'options' => $options,
                'correct' => $correct,
                'explanation' => self::str($row, 'quiz', 'explanation'),
            ];
        }

        return new self($questions);
    }
}
