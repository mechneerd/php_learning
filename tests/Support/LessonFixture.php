<?php

namespace Tests\Support;

use App\Enums\BlockType;
use App\Enums\CodeTier;
use App\Enums\ContentStatus;
use App\Enums\DiagramKind;
use App\Enums\DiagramSource;
use App\Enums\ProvenanceSource;
use App\Models\Chapter;
use App\Models\CodeExample;
use App\Models\Diagram;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Stage;

/**
 * Builds a fully populated published lesson: one block of every type,
 * one linked diagram and one linked code example.
 */
final class LessonFixture
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function full(array $overrides = []): Lesson
    {
        $stage = Stage::factory()->create([
            'number' => 2,
            'slug' => 'object-core',
            'name' => 'Object Core',
        ]);

        $chapter = Chapter::factory()->create([
            'number' => 3,
            'title' => 'Objects and Classes',
            'slug' => 'objects-and-classes',
            'page_printed_from' => 21,
            'page_printed_to' => 78,
            'page_pdf_from' => 41,
            'page_pdf_to' => 97,
        ]);

        $section = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'title' => 'A First Object (or Two)',
            'slug' => 'a-first-object',
            'level' => 2,
            'ord' => 2,
            'page_printed_from' => 22,
            'page_printed_to' => 23,
            'page_pdf_from' => 42,
            'page_pdf_to' => 43,
        ]);

        $lesson = Lesson::factory()->create(array_merge([
            'stage_id' => $stage->id,
            'chapter_id' => $chapter->id,
            'section_id' => $section->id,
            'title' => 'A First Object (or Two)',
            'slug' => 'ch3-a-first-object',
            'summary' => 'Instantiate a class and talk to the resulting object.',
            'status' => ContentStatus::Published,
            'source' => ProvenanceSource::Book,
            'ord' => 1,
            'est_minutes' => 8,
            'page_printed_from' => 22,
            'page_printed_to' => 23,
            'page_pdf_from' => 42,
            'page_pdf_to' => 43,
        ], $overrides));

        $diagram = Diagram::factory()->create([
            'lesson_id' => $lesson->id,
            'kind' => DiagramKind::ClassDiagram,
            'title' => 'Class versus object',
            'mermaid_source' => "classDiagram\n    class ShopProduct {\n        +string title\n        +__construct()\n    }\n    ShopProduct <.. ShopProduct : instance",
            'source' => DiagramSource::Ai,
            'status' => ContentStatus::Published,
        ]);

        $example = CodeExample::factory()->create([
            'lesson_id' => $lesson->id,
            'tier' => CodeTier::Starter,
            'title' => 'First object',
            'listing_ref' => '03.01',
            'code' => "<?php\n\n\$product = new ShopProduct('Examining PHP Objects', 'Matt Zandstra', 24.99);\n\necho \$product->title;",
            'expected_output' => 'Examining PHP Objects',
            'status' => ContentStatus::Published,
            'source' => ProvenanceSource::Book,
            'page_printed_from' => 22,
            'page_printed_to' => 23,
            'page_pdf_from' => 42,
            'page_pdf_to' => 43,
        ]);

        foreach (self::blocks($diagram, $example) as $index => [$type, $payload]) {
            $lesson->blocks()->create([
                'ord' => $index + 1,
                'type' => $type,
                'payload' => $payload,
                'source' => $type === BlockType::ModernPanel ? ProvenanceSource::Ai : ProvenanceSource::Book,
                'page_printed_from' => 22,
                'page_printed_to' => 23,
                'page_pdf_from' => 42,
                'page_pdf_to' => 43,
            ]);
        }

        return $lesson;
    }

    /**
     * One representative, validator-approved payload per block type.
     *
     * @return list<array{BlockType, array<string, mixed>}>
     */
    public static function blocks(Diagram $diagram, CodeExample $example): array
    {
        return [
            [BlockType::Heading, ['text' => 'What is an object?']],
            [BlockType::Paragraph, ['markdown' => "An object bundles state and behaviour behind a single name.\n\nIt is created from a class."]],
            [BlockType::Bullets, ['items' => ['State lives in properties', 'Behaviour lives in methods', 'Identity is the specific instance']]],
            [BlockType::Callout, ['text' => 'A class is the mould; each object is a casting poured from it.', 'variant' => 'tip']],
            [BlockType::Code, ['lang' => 'php', 'code' => "<?php\n\n\$product = new ShopProduct();"]],
            [BlockType::Output, ['text' => 'ShopProduct Object']],
            [BlockType::Table, ['headers' => ['Keyword', 'Meaning'], 'rows' => [['public', 'visible from anywhere'], ['private', 'visible inside the class']]]],
            [BlockType::Diagram, ['diagram_id' => $diagram->id]],
            [BlockType::BookQuote, ['text' => 'An object is a thing with distinct boundaries.', 'attribution' => 'Zandstra, Ch. 3']],
            [BlockType::ModernPanel, ['book' => 'The book builds the constructor by hand.', 'modern' => 'PHP 8 promotes constructor parameters to properties in one step.', 'why' => 'Less repetition, same runtime behaviour.']],
            [BlockType::PrereqList, ['items' => ['Variables, types and functions', 'Declaring a class']]],
            [BlockType::ExerciseRef, ['labels' => ['Create a ShopProduct and print its price']]],
            [BlockType::QuizRef, ['labels' => ['What does the new operator return?']]],
            [BlockType::CardRefs, ['labels' => ['constructor property promotion']]],
            [BlockType::InterviewRef, ['labels' => ['Explain the difference between a class and an object.']]],
            [BlockType::Tabs, ['tabs' => [['label' => 'Book', 'content' => 'The book uses ShopProduct throughout.'], ['label' => 'Modern', 'content' => 'Modern PHP adds readonly and promotion.']]]],
            [BlockType::Image, ['figure_ref' => '3-1', 'caption' => 'The ShopProduct class sketch', 'page_pdf' => 45]],
            [BlockType::CodeExample, ['code_example_id' => $example->id]],
        ];
    }
}
