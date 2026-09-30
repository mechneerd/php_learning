<?php

use App\Enums\BlockType;
use App\Services\Content\BlockValidator;
use App\Services\Content\InvalidBlockPayload;

beforeEach(function () {
    $this->validator = new BlockValidator;
});

it('accepts and normalizes a payload for every block type', function (BlockType $type, array $payload) {
    $clean = $this->validator->validate($type, $payload);

    expect($clean)->toBeArray()->not->toBeEmpty();
})->with([
    'heading' => [BlockType::Heading, ['text' => 'What is an object?']],
    'paragraph' => [BlockType::Paragraph, ['markdown' => 'Objects bundle state and behaviour.']],
    'bullets' => [BlockType::Bullets, ['items' => ['one', 'two']]],
    'callout' => [BlockType::Callout, ['text' => 'Watch out.', 'variant' => 'warn']],
    'code' => [BlockType::Code, ['lang' => 'php', 'code' => '<?php echo 1;']],
    'output' => [BlockType::Output, ['text' => '1']],
    'table' => [BlockType::Table, ['headers' => ['a'], 'rows' => [['b']]]],
    'diagram' => [BlockType::Diagram, ['diagram_id' => 7]],
    'book_quote' => [BlockType::BookQuote, ['text' => 'Quote.', 'attribution' => 'Book']],
    'modern_panel' => [BlockType::ModernPanel, ['book' => 'b', 'modern' => 'm', 'why' => 'w']],
    'prereq_list' => [BlockType::PrereqList, ['items' => ['know classes']]],
    'exercise_ref' => [BlockType::ExerciseRef, ['labels' => ['task'], 'ids' => [3]]],
    'quiz_ref' => [BlockType::QuizRef, ['labels' => ['question']]],
    'card_refs' => [BlockType::CardRefs, ['labels' => ['term']]],
    'interview_ref' => [BlockType::InterviewRef, ['labels' => ['ask this']]],
    'tabs' => [BlockType::Tabs, ['tabs' => [['label' => 'A', 'content' => 'first'], ['label' => 'B', 'content' => 'second']]]],
    'image' => [BlockType::Image, ['figure_ref' => '3-1', 'caption' => 'Sketch', 'page_pdf' => 45]],
    'code_example' => [BlockType::CodeExample, ['code_example_id' => 12]],
]);

it('drops unknown keys and trims strings', function () {
    $clean = $this->validator->validate(BlockType::Heading, [
        'text' => '  Hello  ',
        'junk' => 'remove me',
    ]);

    expect($clean)->toBe(['text' => 'Hello']);
});

it('rejects a missing required field', function () {
    expect(fn () => $this->validator->validate(BlockType::Code, ['lang' => 'php']))
        ->toThrow(InvalidBlockPayload::class, 'requires payload field [code]');
});

it('rejects wrong field types', function () {
    expect(fn () => $this->validator->validate(BlockType::Bullets, ['items' => 'not a list']))
        ->toThrow(InvalidBlockPayload::class, 'array of strings');
});

it('rejects empty lists', function () {
    expect(fn () => $this->validator->validate(BlockType::CardRefs, ['labels' => []]))
        ->toThrow(InvalidBlockPayload::class, 'requires payload field [labels]');
});

it('rejects malformed tabs', function () {
    expect(fn () => $this->validator->validate(BlockType::Tabs, ['tabs' => [['content' => 'no label']]]))
        ->toThrow(InvalidBlockPayload::class, 'label');
});

it('coerces numeric strings to integers for id references', function () {
    $clean = $this->validator->validate(BlockType::CodeExample, ['code_example_id' => '42']);

    expect($clean)->toBe(['code_example_id' => 42]);
});
