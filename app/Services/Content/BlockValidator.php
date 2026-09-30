<?php

namespace App\Services\Content;

use App\Enums\BlockType;

/**
 * Validates (and normalizes) the type-specific JSON payload of a lesson block.
 *
 * Each field spec is "kind" (required) or "kind?" (optional). Supported kinds:
 * string, text, int, string[], int[], rows, tabs.
 */
final class BlockValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws InvalidBlockPayload
     */
    public function validate(BlockType $type, array $payload): array
    {
        $spec = match ($type) {
            BlockType::Heading => ['text' => 'string', 'level' => 'int?'],
            BlockType::Paragraph => ['markdown' => 'text'],
            BlockType::Bullets => ['items' => 'string[]'],
            BlockType::Callout => ['text' => 'text', 'variant' => 'string?'],
            BlockType::Code => ['lang' => 'string', 'code' => 'text'],
            BlockType::Output => ['text' => 'text'],
            BlockType::Table => ['headers' => 'string[]', 'rows' => 'rows'],
            BlockType::Diagram => ['diagram_id' => 'int'],
            BlockType::BookQuote => ['text' => 'text', 'attribution' => 'string?'],
            BlockType::ModernPanel => ['book' => 'text', 'modern' => 'text', 'why' => 'text'],
            BlockType::PrereqList => ['items' => 'string[]'],
            BlockType::ExerciseRef => ['labels' => 'string[]', 'ids' => 'int[]?'],
            BlockType::QuizRef => ['labels' => 'string[]', 'ids' => 'int[]?'],
            BlockType::CardRefs => ['labels' => 'string[]'],
            BlockType::InterviewRef => ['labels' => 'string[]'],
            BlockType::Tabs => ['tabs' => 'tabs'],
            BlockType::Image => ['figure_ref' => 'string', 'caption' => 'string?', 'page_pdf' => 'int?'],
            BlockType::CodeExample => ['code_example_id' => 'int'],
        };

        return $this->check($type, $payload, $spec);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $spec
     * @return array<string, mixed>
     */
    private function check(BlockType $type, array $payload, array $spec): array
    {
        $clean = [];

        foreach ($spec as $field => $kind) {
            $optional = str_ends_with($kind, '?');
            $kind = $optional ? substr($kind, 0, -1) : $kind;

            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                if ($optional) {
                    continue;
                }

                throw InvalidBlockPayload::missing($type, $field);
            }

            $value = $payload[$field];

            $clean[$field] = match ($kind) {
                'string' => $this->string($type, $field, $value, multiline: false),
                'text' => $this->string($type, $field, $value, multiline: true),
                'int' => $this->int($type, $field, $value),
                'string[]' => $this->stringList($type, $field, $value),
                'int[]' => $this->intList($type, $field, $value),
                'rows' => $this->rows($type, $field, $value),
                'tabs' => $this->tabs($type, $field, $value),
                default => throw InvalidBlockPayload::wrongType($type, $field, 'a supported payload kind'),
            };
        }

        return $clean;
    }

    private function string(BlockType $type, string $field, mixed $value, bool $multiline): string
    {
        if (! is_string($value)) {
            throw InvalidBlockPayload::wrongType($type, $field, 'a string');
        }

        $value = trim($value);

        if ($value === '') {
            throw InvalidBlockPayload::missing($type, $field);
        }

        return $value;
    }

    private function int(BlockType $type, string $field, mixed $value): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            throw InvalidBlockPayload::wrongType($type, $field, 'an integer');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(BlockType $type, string $field, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidBlockPayload::wrongType($type, $field, 'an array of strings');
        }

        $list = [];

        foreach (array_values($value) as $i => $item) {
            if (! is_scalar($item)) {
                throw InvalidBlockPayload::wrongType($type, "{$field}[{$i}]", 'a string');
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $list[] = $item;
            }
        }

        if ($list === []) {
            throw InvalidBlockPayload::missing($type, $field);
        }

        return $list;
    }

    /**
     * @return list<int>
     */
    private function intList(BlockType $type, string $field, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidBlockPayload::wrongType($type, $field, 'an array of integers');
        }

        $list = [];

        foreach (array_values($value) as $i => $item) {
            if (is_string($item) && ctype_digit($item)) {
                $item = (int) $item;
            }

            if (! is_int($item)) {
                throw InvalidBlockPayload::wrongType($type, "{$field}[{$i}]", 'an integer');
            }

            $list[] = $item;
        }

        return $list;
    }

    /**
     * @return list<list<string>>
     */
    private function rows(BlockType $type, string $field, mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw InvalidBlockPayload::wrongType($type, $field, 'a non-empty array of rows');
        }

        $rows = [];

        foreach (array_values($value) as $i => $row) {
            if (! is_array($row)) {
                throw InvalidBlockPayload::wrongType($type, "{$field}[{$i}]", 'an array of cells');
            }

            $rows[] = array_values(array_map(
                static fn (mixed $cell): string => is_scalar($cell) ? trim((string) $cell) : '',
                $row,
            ));
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, content: string}>
     */
    private function tabs(BlockType $type, string $field, mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw InvalidBlockPayload::wrongType($type, $field, 'a non-empty array of tabs');
        }

        $tabs = [];

        foreach (array_values($value) as $i => $tab) {
            if (! is_array($tab)) {
                throw InvalidBlockPayload::wrongType($type, "{$field}[{$i}]", 'a tab object');
            }

            $tabs[] = [
                'label' => $this->string($type, "{$field}[{$i}].label", $tab['label'] ?? null, multiline: false),
                'content' => $this->string($type, "{$field}[{$i}].content", $tab['content'] ?? null, multiline: true),
            ];
        }

        return $tabs;
    }
}
