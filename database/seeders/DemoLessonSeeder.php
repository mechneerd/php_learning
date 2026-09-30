<?php

namespace Database\Seeders;

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
use App\Models\PdfPage;
use App\Models\Section;
use App\Models\Stage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seeds the demo course: Chapter 3 "Object Basics" as 23 published lessons.
 *
 * Every lesson maps to a real detected section of the imported book. Code
 * examples are transcribed verbatim from the book's listings (source = book);
 * explanatory text, diagrams and practice references are AI scaffolding
 * (source = ai). Book quotes are extracted from the imported PDF page text at
 * seed time and skipped when extraction quality is too poor to quote.
 *
 * Idempotent: lessons are matched by slug, examples and diagrams by their
 * natural key, and blocks are rebuilt deterministically on every run.
 */
final class DemoLessonSeeder extends Seeder
{
    /** @var array<string, true> Quotes already used by an earlier lesson. */
    private array $usedQuotes = [];

    public function run(): void
    {
        $chapter = Chapter::query()->where('number', 3)->orderBy('id')->first();

        if ($chapter === null) {
            Log::warning('DemoLessonSeeder: no chapter 3 found. Run php artisan book:import <pdf> --detect first.');

            return;
        }

        $stage = Stage::query()->where('slug', 'object-core')->first();

        if ($stage === null) {
            $this->call(StageSeeder::class);
            $stage = Stage::query()->where('slug', 'object-core')->firstOrFail();
        }

        $sections = $chapter->sections()->orderBy('ord')->get();
        $ord = 0;

        foreach ($this->lessons() as $index => $def) {
            $section = $this->matchSection($sections, $def['section'], $index);

            if ($section === null) {
                Log::warning("DemoLessonSeeder: section [{$def['section']}] not found in chapter 3; skipped.");

                continue;
            }

            $this->seedLesson($stage, $chapter, $section, $def, ++$ord);
        }
    }

    /**
     * @param  Collection<int, Section>  $sections
     */
    private function matchSection($sections, string $title, int $index): ?Section
    {
        $section = $sections->first(fn (Section $s): bool => $s->title === $title);

        if ($section !== null) {
            return $section;
        }

        $slug = Str::slug($title);

        return $sections->first(fn (Section $s): bool => $s->slug === $slug)
            ?? $sections->first(fn (Section $s): bool => $s->ord === $index);
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function seedLesson(Stage $stage, Chapter $chapter, Section $section, array $def, int $ord): Lesson
    {
        $lesson = Lesson::updateOrCreate(['slug' => 'ch'.$chapter->number.'-'.Str::slug($def['section'])], [
            'stage_id' => $stage->id,
            'chapter_id' => $chapter->id,
            'section_id' => $section->id,
            'title' => $def['section'],
            'summary' => $def['summary'],
            'status' => ContentStatus::Published,
            'est_minutes' => $def['est'],
            'ord' => $ord,
            'source' => ProvenanceSource::Ai,
            'page_printed_from' => $section->page_printed_from,
            'page_printed_to' => $section->page_printed_to,
            'page_pdf_from' => $section->page_pdf_from,
            'page_pdf_to' => $section->page_pdf_to,
            'reviewed_by' => null,
            'is_outdated' => false,
        ]);

        $exampleIds = $this->seedExamples($lesson, $section, $def['examples'] ?? []);
        $diagramIds = $this->seedDiagrams($lesson, $def['diagrams'] ?? []);
        $this->seedBlocks($lesson, $section, $def['blocks'], $exampleIds, $diagramIds);

        return $lesson;
    }

    /**
     * @param  list<array<string, mixed>>  $examples
     * @return array<string, int>
     */
    private function seedExamples(Lesson $lesson, Section $section, array $examples): array
    {
        $ids = [];

        foreach ($examples as $index => $example) {
            $row = CodeExample::updateOrCreate(
                ['lesson_id' => $lesson->id, 'listing_ref' => $example['ref']],
                [
                    'concept_id' => null,
                    'tier' => $example['tier'] ?? CodeTier::Core,
                    'title' => $example['title'],
                    'code' => $example['code'],
                    'expected_output' => $example['output'] ?? null,
                    'explanation' => $example['why'] ?? null,
                    'common_mistake' => $example['mistake'] ?? null,
                    'syntax_notes' => $example['syntax'] ?? null,
                    'external_ref' => null,
                    'ord' => $index + 1,
                    'source' => ProvenanceSource::Book,
                    'status' => ContentStatus::Published,
                    'page_printed_from' => $section->page_printed_from,
                    'page_printed_to' => $section->page_printed_to,
                    'page_pdf_from' => $section->page_pdf_from,
                    'page_pdf_to' => $section->page_pdf_to,
                    'reviewed_by' => null,
                    'is_outdated' => false,
                ]
            );

            $ids[$example['ref']] = $row->id;
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $diagrams
     * @return array<string, int>
     */
    private function seedDiagrams(Lesson $lesson, array $diagrams): array
    {
        $ids = [];

        foreach ($diagrams as $diagram) {
            $row = Diagram::updateOrCreate(
                ['lesson_id' => $lesson->id, 'title' => $diagram['title']],
                [
                    'kind' => $diagram['kind'],
                    'mermaid_source' => $diagram['mermaid'],
                    'source' => DiagramSource::Ai,
                    'figure_ref' => null,
                    'page_pdf' => null,
                    'status' => ContentStatus::Published,
                ]
            );

            $ids[$diagram['title']] = $row->id;
        }

        return $ids;
    }

    /**
     * Rebuilds the lesson's blocks in order.
     *
     * @param  list<array{0: string, 1: array<string, mixed>}>  $blocks
     * @param  array<string, int>  $exampleIds
     * @param  array<string, int>  $diagramIds
     */
    private function seedBlocks(Lesson $lesson, Section $section, array $blocks, array $exampleIds, array $diagramIds): void
    {
        $lesson->blocks()->delete();
        $ord = 0;

        foreach ($blocks as [$type, $payload]) {
            $blockType = BlockType::from($type);

            if (($ref = $payload['_ref'] ?? null) !== null) {
                unset($payload['_ref']);

                $payload = match ($blockType) {
                    BlockType::CodeExample => ['code_example_id' => $exampleIds[$ref] ?? null],
                    BlockType::Diagram => ['diagram_id' => $diagramIds[$ref] ?? null],
                    default => $payload,
                };

                if (reset($payload) === null) {
                    continue;
                }
            }

            if ($blockType === BlockType::BookQuote) {
                $quote = $this->quoteFromPages($section);

                if ($quote === null) {
                    continue;
                }

                $payload = [
                    'text' => $quote,
                    'attribution' => 'Matt Zandstra, PHP 8 Objects, Patterns and Practice, Ch. 3',
                ];
            }

            $lesson->blocks()->create([
                'ord' => ++$ord,
                'type' => $blockType,
                'payload' => $payload,
                'source' => in_array($blockType, [BlockType::CodeExample, BlockType::BookQuote], true)
                    ? ProvenanceSource::Book
                    : ProvenanceSource::Ai,
                'page_printed_from' => $section->page_printed_from,
                'page_printed_to' => $section->page_printed_to,
                'page_pdf_from' => $section->page_pdf_from,
                'page_pdf_to' => $section->page_pdf_to,
            ]);
        }
    }

    /**
     * Pulls a quotable first paragraph out of the imported PDF page text.
     * Returns null when extraction quality is too poor to quote safely.
     */
    private function quoteFromPages(Section $section): ?string
    {
        if ($section->page_pdf_from === null) {
            return null;
        }

        $book = $section->chapter()->first()?->book;

        if ($book === null) {
            return null;
        }

        $documentId = $book->source_pdf_document_id ?? $book->activePdfDocument?->id;

        if ($documentId === null) {
            return null;
        }

        $pages = PdfPage::query()
            ->where('pdf_document_id', $documentId)
            ->whereBetween('page_pdf', [$section->page_pdf_from, $section->page_pdf_to ?? $section->page_pdf_from])
            ->orderBy('page_pdf')
            ->get();

        foreach ($pages as $page) {
            $quote = $this->cleanQuote($page->text);

            if ($quote === null || isset($this->usedQuotes[$quote])) {
                continue;
            }

            $this->usedQuotes[$quote] = true;

            return $quote;
        }

        return null;
    }

    private function cleanQuote(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r?\n/', $raw) ?: [];
        $kept = [];
        $started = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Skip running heads, page numbers, notes and code.
            if (preg_match('/^(chapter\s+\d|www\.|©|note\b|\d{1,3}\s*$)/i', $line)) {
                continue;
            }

            // Skip diagnostics and extraction of error output.
            if (preg_match('/^(TypeError|Warning|Notice|Fatal error|Deprecated)/', $line) || str_contains($line, 'popp\\ch')) {
                continue;
            }

            if (preg_match('#^(//|/\*|\$|object\(|class\s+\w|public\s|private\s|protected\s|function\s|\{|\}|\);)#', $line)) {
                continue;
            }

            // Leading short lines without sentence punctuation are running
            // heads ("Setting Properties in a Class"); wait for real prose.
            if (! $started) {
                if (mb_strlen($line) < 60 && ! preg_match('/[.!?]$/', $line)) {
                    continue;
                }

                // Continuation fragments ("but is likely..."), table rows and
                // diagnostics start lowercase or with symbols; error lead-ins
                // end with a colon. Only a capital or quote may open a quote.
                if (! preg_match('/^(["\']|[A-Z])/', $line) || str_ends_with($line, ':')) {
                    continue;
                }

                $started = true;
            }

            $kept[] = $line;
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $kept)));

        if (mb_strlen($text) < 60) {
            return null;
        }

        // Prefer a complete first sentence between 60 and 300 characters.
        if (preg_match('/^(.{60,300}?[.!?])\s+\S/u', $text, $m) === 1) {
            return $m[1];
        }

        $cut = mb_substr($text, 0, 300);

        if (preg_match('/^(.*[.!?])\s+\S/u', $cut, $m) === 1) {
            return $m[1];
        }

        return $cut;
    }

    /**
     * Practice references injected into core lessons (labels are AI content;
     * Phase 3 wires them to real exercise, quiz and card records).
     *
     * @param  list<string>  $cards
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private static function practice(string $exercise, string $quiz, array $cards, string $interview): array
    {
        return [
            ['exercise_ref', ['labels' => [$exercise]]],
            ['quiz_ref', ['labels' => [$quiz]]],
            ['card_refs', ['labels' => $cards]],
            ['interview_ref', ['labels' => [$interview]]],
        ];
    }

    /**
     * Chapter 3 lesson content, split across parts for readability.
     *
     * @return list<array<string, mixed>>
     */
    private function lessons(): array
    {
        return array_merge(
            $this->lessonsOne(),
            $this->lessonsTwo(),
            $this->lessonsThree(),
            $this->lessonsFour(),
            $this->lessonsFive(),
        );
    }

    /**
     * Sections 0-4: Classes and Objects through Working with Methods.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsOne(): array
    {
        return [
            // ------------------------------------------------------ 0: Classes and Objects
            [
                'section' => 'Classes and Objects',
                'summary' => 'What a class is, what an object is, and how the two relate.',
                'est' => 6,
                'diagrams' => [
                    [
                        'title' => 'Class template and instances',
                        'kind' => DiagramKind::Flowchart,
                        'mermaid' => <<<'MMD'
                            flowchart TD
                                A["class ShopProduct (the template)"] --> B["new ShopProduct()"]
                                B --> C["$product1 - object #1"]
                                B --> D["$product2 - object #2"]
                                C --> E["each object: own properties, own identity"]
                                D --> E
                            MMD,
                    ],
                ],
                'examples' => [],
                'blocks' => [
                    ['prereq_list', ['items' => ['PHP variables, types and functions (Stage 1)', 'Running a PHP script from the command line']]],
                    ['bullets', ['items' => ['A class defines a type: data (properties) plus behaviour (methods)', 'An object is one instance of that type', 'The same class can produce many distinct objects']]],
                    ['paragraph', ['markdown' => 'Chapter 3 builds one running example, `ShopProduct`, from an empty class up to a typed, visible, inherited design. Follow the listings in order and every later chapter will make more sense.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'Class template and instances']],
                    ['callout', ['text' => 'A class is the mould; each object is a casting poured from it. Identical shape, separate identity.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['Class = template and type', 'Object = instance of a class', 'new creates the instance']]],
                    ...self::practice(
                        'List three real-world types your app models and what one instance of each holds.',
                        'What is the difference between a class and an object?',
                        ['class', 'object', 'type', 'instance'],
                        'Explain the mould-and-casting analogy to a junior developer.'
                    ),
                ],
            ],

            // ------------------------------------------------------ 1: A First Class
            [
                'section' => 'A First Class',
                'summary' => 'Declare your first class and understand it as a new type.',
                'est' => 6,
                'examples' => [
                    [
                        'ref' => '03.01',
                        'title' => 'An empty class',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                // class body
                            }
                            PHP,
                        'why' => 'This is already a legal class. It defines a new type even though it has no members yet.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Declare a class with the class keyword', 'A class defines a type, not a value', 'Class names conventionally use PascalCase']]],
                    ['paragraph', ['markdown' => 'Declaring `ShopProduct` gives you a category of data you can use throughout your scripts. Nothing is instantiated yet - the class itself is the blueprint.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.01']],
                    ['callout', ['text' => 'A class declaration is a statement, not an expression: nothing runs until you instantiate. And a class name must be a valid identifier - it cannot start with a digit.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['class keyword opens the declaration', 'The body can stay empty', 'Declaring the type is already useful']]],
                    ...self::practice(
                        'Declare an empty Invoice class and prove PHP accepts it with php -l.',
                        'Does declaring a class create any object?',
                        ['class declaration', 'PascalCase'],
                        'Why does defining a type matter before it has any methods?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 2: A First Object (or Two)
            [
                'section' => 'A First Object (or Two)',
                'summary' => 'Instantiate a class with new and see that objects have identity.',
                'est' => 8,
                'diagrams' => [
                    [
                        'title' => 'new hands back a fresh object',
                        'kind' => DiagramKind::Sequence,
                        'mermaid' => <<<'MMD'
                            sequenceDiagram
                                participant C as Client code
                                participant S as ShopProduct class
                                C->>S: new ShopProduct()
                                S-->>C: fresh object instance
                                Note over C: $product1 and $product2 are distinct objects
                            MMD,
                    ],
                ],
                'examples' => [
                    [
                        'ref' => '03.02',
                        'title' => 'Two instances of one class',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct();
                            $product2 = new ShopProduct();
                            PHP,
                        'why' => 'The new operator uses the class as a template and returns a brand-new object each time.',
                    ],
                    [
                        'ref' => '03.03',
                        'title' => 'Proving object identity with var_dump()',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            var_dump($product1);
                            var_dump($product2);
                            PHP,
                        'output' => "object(ShopProduct)#235 (0) {\n}\n\nobject(ShopProduct)#234 (0) {\n}",
                        'why' => 'Each object carries its own internal identifier, so the dumps show different ids even though both are ShopProduct.',
                        'mistake' => 'Assuming two objects from one class are interchangeable values - == compares members, === compares identity.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['new instantiates a class', 'Every object has a unique identity', 'Objects of one type share a shape, not state']]],
                    ['paragraph', ['markdown' => 'If a class is a template, an object is data structured according to that template. `$product1` and `$product2` are the same type but separate entities - like two ducks pressed from one mould.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'new hands back a fresh object']],
                    ['code_example', ['_ref' => '03.02']],
                    ['code_example', ['_ref' => '03.03']],
                    ['callout', ['text' => 'PHP reuses object identifiers over a process lifetime - a different id means a different object, not a different class.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['new is required to create an object', 'Identity is per instance', 'State comes later, with properties']]],
                    ...self::practice(
                        'Create three ShopProduct objects and dump them to confirm distinct ids.',
                        'What does the new operator return?',
                        ['new operator', 'instance', 'object identity'],
                        'Two objects from one class: same type or same value? Defend your answer.'
                    ),
                ],
            ],

            // ------------------------------------------------------ 3: Setting Properties in a Class
            [
                'section' => 'Setting Properties in a Class',
                'summary' => 'Declare properties, read and write them, and avoid dynamic properties.',
                'est' => 8,
                'examples' => [
                    [
                        'ref' => '03.04',
                        'title' => 'Declaring properties with defaults',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $title = "default product";
                                public $producerMainName = "main name";
                                public $producerFirstName = "first name";
                                public $price = 0;
                            }
                            PHP,
                        'why' => 'Every object starts prepopulated with these defaults, and client code can rely on the properties existing.',
                    ],
                    [
                        'ref' => '03.06',
                        'title' => 'Reading and writing properties from client code',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct();
                            $product2 = new ShopProduct();
                            $product1->title = "My Antonia";
                            $product2->title = "Catch 22";
                            PHP,
                        'why' => 'The object operator -> reads and writes properties per instance, so each object keeps its own state.',
                    ],
                    [
                        'ref' => '03.07',
                        'title' => 'Dynamic property assignment (bad practice)',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product1->arbitraryAddition = "treehouse";
                            PHP,
                        'why' => 'PHP allows this, but the property was never declared in the class, so no contract guarantees it exists for other objects.',
                        'mistake' => 'Relying on dynamically added properties - a class defines its type, and undeclared fields break that promise.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Properties hold data that varies per object', 'Each property needs a visibility keyword', 'Declared properties are part of the class contract']]],
                    ['paragraph', ['markdown' => 'A property (member variable) looks like a normal variable inside the class, but you must precede it with `public`, `protected` or `private`. For now everything is public so client code can reach it.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.04']],
                    ['code_example', ['_ref' => '03.06']],
                    ['table', ['headers' => ['Practice', 'Verdict'], 'rows' => [['Declare properties in the class', 'The contract every instance honours'], ['Assign per instance with ->', 'Normal, intended state setting'], ['Add undeclared fields at runtime', 'Discouraged: breaks the type contract']]]],
                    ['code_example', ['_ref' => '03.07']],
                    ['callout', ['text' => 'A typo like $product1->producerSecondName = "Jackson" creates a brand-new dynamic property instead of failing - your class silently grows fields nobody declared.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Defaults apply at instantiation', '-> gives per-object state', 'Declare everything you intend to exist']]],
                    ...self::practice(
                        'Give ShopProduct four declared properties and set them on two separate objects.',
                        'Why are dynamic properties discouraged?',
                        ['property', 'object operator', 'visibility keyword'],
                        'What contract does a class offer the code that uses it?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 4: Working with Methods
            [
                'section' => 'Working with Methods',
                'summary' => 'Give objects behaviour with methods and the $this pseudo-variable.',
                'est' => 8,
                'diagrams' => [
                    [
                        'title' => 'Method dispatch and $this',
                        'kind' => DiagramKind::Flowchart,
                        'mermaid' => <<<'MMD'
                            flowchart LR
                                A["$product1->getProducer()"] --> B["getProducer() body runs"]
                                B --> C["$this refers to $product1"]
                                C --> D["reads $this->producerFirstName"]
                            MMD,
                    ],
                ],
                'examples' => [
                    [
                        'ref' => '03.13',
                        'title' => 'A method that reads its own object',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $title = "default product";
                                public $producerMainName = "main name";
                                public $producerFirstName = "first name";
                                public $price = 0;

                                public function getProducer()
                                {
                                    return $this->producerFirstName . " "
                                        . $this->producerMainName;
                                }
                            }
                            PHP,
                        'why' => '$this is the pseudo-variable a method uses to refer to the object it was called on.',
                        'syntax' => 'Declare visibility explicitly on every method - PSR-12 requires it, and omitting it silently defaults to public.',
                    ],
                    [
                        'ref' => '03.14',
                        'title' => 'Calling a method with ->',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct();
                            $product1->title = "My Antonia";
                            $product1->producerMainName = "Cather";
                            $product1->producerFirstName = "Willa";
                            $product1->price = 5.99;

                            print "author: {$product1->getProducer()}\n";
                            PHP,
                        'output' => 'author: Willa Cather',
                        'why' => 'The call needs parentheses even with no arguments, and $this inside resolves to $product1.',
                        'mistake' => 'Forgetting the parentheses - a method name without () is not a call.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Methods give objects behaviour', 'They are declared inside the class body', '$this means "the current instance"']]],
                    ['paragraph', ['markdown' => 'Reading a full producer name by concatenating properties from outside the object is tedious and error-prone. A method moves that drudgery inside the object, where the data lives.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'Method dispatch and $this']],
                    ['code_example', ['_ref' => '03.13']],
                    ['code_example', ['_ref' => '03.14']],
                    ['callout', ['text' => 'Read $this->producerFirstName as "the $producerFirstName property of the current instance". If you can substitute that phrase and the sentence still makes sense, you are reading the code correctly.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['-> invokes a method on a specific instance', 'Methods return data or mutate state', '$this binds to the receiver']]],
                    ...self::practice(
                        'Add getProducer() to ShopProduct and print the producer of two different objects.',
                        'What does $this refer to inside a method?',
                        ['method', '$this', 'object operator'],
                        'Why move string assembly into the object rather than doing it in client code?'
                    ),
                ],
            ],
        ];
    }

    /**
     * Sections 5-9: Constructor through Primitive Types.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsTwo(): array
    {
        return [
            // ------------------------------------------------------ 5: Creating a Constructor Method
            [
                'section' => 'Creating a Constructor Method',
                'summary' => 'Initialise objects automatically with __construct().',
                'est' => 8,
                'examples' => [
                    [
                        'ref' => '03.15',
                        'title' => 'A constructor that sets every property',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $title;
                                public $producerMainName;
                                public $producerFirstName;
                                public $price = 0;

                                public function __construct(
                                    $title,
                                    $firstName,
                                    $mainName,
                                    $price
                                ) {
                                    $this->title = $title;
                                    $this->producerFirstName = $firstName;
                                    $this->producerMainName = $mainName;
                                    $this->price = $price;
                                }

                                public function getProducer()
                                {
                                    return $this->producerFirstName . " "
                                        . $this->producerMainName;
                                }
                            }
                            PHP,
                        'why' => '__construct() runs automatically when new is used, so instantiation and setup happen in one statement.',
                        'syntax' => 'Two leading underscores: __construct(). Pre-PHP 5 class-named constructors were removed in PHP 8.',
                    ],
                    [
                        'ref' => '03.16',
                        'title' => 'Instantiating through the constructor',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct(
                                "My Antonia",
                                "Willa",
                                "Cather", 5.99
                            );
                            print "author: {$product1->getProducer()}\n";
                            PHP,
                        'output' => 'author: Willa Cather',
                        'why' => 'Arguments supplied to new are passed to the constructor, which assigns them to properties with $this.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['A constructor runs on instantiation', '__construct() is the PHP 8 spelling', 'It guarantees properties start initialised']]],
                    ['paragraph', ['markdown' => 'Setting five properties by hand from client code is tedious and impossible to enforce. A constructor is the method PHP invokes when an object is created - use it to do that setup once, inside the class.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.15']],
                    ['code_example', ['_ref' => '03.16']],
                    ['callout', ['text' => 'The method name starts with exactly two underscores. Class-named constructors (ShopProduct()) were deprecated in PHP 7 and no longer work in PHP 8.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['new + arguments = constructor call', '$this->x = $x assigns state', 'Client code gets a ready object']]],
                    ...self::practice(
                        'Convert your ShopProduct properties to be set only through __construct().',
                        'When is __construct() called?',
                        ['constructor', '__construct', 'instantiation'],
                        'Why is one setup statement safer than five assignments in client code?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 6: Constructor Property Promotion
            [
                'section' => 'Constructor Property Promotion',
                'summary' => 'Collapse property, argument and assignment boilerplate with PHP 8 promotion.',
                'est' => 6,
                'examples' => [
                    [
                        'ref' => '03.17',
                        'title' => 'Promoted constructor properties',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public function __construct(
                                    public $title,
                                    public $producerFirstName,
                                    public $producerMainName,
                                    public $price
                                ) {
                                }

                                public function getProducer()
                                {
                                    return $this->producerFirstName . " "
                                        . $this->producerMainName;
                                }
                            }
                            PHP,
                        'why' => 'A visibility keyword in the constructor signature declares, types and assigns the property in one step.',
                        'syntax' => 'Promotion sugar expands to exactly the hand-written form: declaration plus assignment in the constructor body.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Promotion merges declaration + parameter + assignment', 'Needs a visibility keyword (or readonly) per parameter', 'The constructor body can stay empty']]],
                    ['paragraph', ['markdown' => 'Without promotion, four properties cost three sets of references: property declarations, constructor parameters, and assignments. PHP 8 collapses all three into the signature.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.17']],
                    ['modern_panel', ['book' => 'The book first builds the class the long way: declare each property, accept each constructor argument, then assign them one by one.', 'modern' => 'PHP 8 constructor property promotion does all three from the parameter list, and PHP 8.2 adds the readonly modifier for immutable promoted properties.', 'why' => 'Read the expanded form first so the shortcut never looks like magic - the runtime behaviour is identical.']],
                    ['callout', ['text' => 'Promotion does not make properties private by itself - the visibility you write in the signature is what the world sees. And you cannot redeclare a promoted property elsewhere in the class.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Less boilerplate, same bytecode', 'Visibility is explicit in the signature', 'Combine with types for full declarations']]],
                    ...self::practice(
                        'Rewrite your hand-built constructor using promotion and diff the behaviour.',
                        'What three things does a promoted parameter replace?',
                        ['constructor property promotion', 'readonly'],
                        'When would you still write the constructor by hand?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 7: Default Arguments and Named Arguments
            [
                'section' => 'Default Arguments and Named Arguments',
                'summary' => 'Optional parameters and PHP 8 named arguments.',
                'est' => 8,
                'examples' => [
                    [
                        'ref' => '03.19',
                        'title' => 'Constructor with default values',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public function __construct(
                                public $title,
                                public $producerFirstName = "",
                                public $producerMainName = "",
                                public $price = 0
                                ) {
                                }

                                // ...
                            }
                            PHP,
                        'why' => 'Parameters with defaults only receive them when the caller omits them, so most calls can stay short.',
                    ],
                    [
                        'ref' => '03.20',
                        'title' => 'A one-argument call',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct("Shop Catalogue");
                            PHP,
                        'why' => 'With defaults in place, a title-only call is legal and the other properties still initialise.',
                    ],
                    [
                        'ref' => '03.21',
                        'title' => 'Named arguments',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct(
                                price: 0.7,
                                title: "Shop Catalogue"
                            );
                            PHP,
                        'why' => 'PHP 8 matches each value to its parameter by name, so order stops mattering and placeholders are unnecessary.',
                        'syntax' => 'name: value - the name is the parameter name without its $ sigil.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['A default makes a parameter optional', 'Callers may then skip trailing arguments', 'PHP 8 named arguments remove ordering worries']]],
                    ['paragraph', ['markdown' => 'Before PHP 8, wanting a custom price but default producer names meant passing empty strings positionally. Named arguments end that: label each value and pass only what you care about.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.19']],
                    ['code_example', ['_ref' => '03.20']],
                    ['code_example', ['_ref' => '03.21']],
                    ['callout', ['text' => 'Named arguments use the parameter name without $: price: 0.7, not $price: 0.7. Mixing named and positional arguments is allowed only after every positional argument.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['Defaults live in the signature', 'Named arguments are order-independent', 'Together they cut call-site noise']]],
                    ...self::practice(
                        'Give three ShopProduct parameters defaults, then set only the price by name.',
                        'What does a default argument value do?',
                        ['default argument', 'named argument'],
                        'Give an API where named arguments genuinely improve readability.'
                    ),
                ],
            ],

            // ------------------------------------------------------ 8: Arguments and Types
            [
                'section' => 'Arguments and Types',
                'summary' => 'Why argument data needs a type, and what happens when it gets the wrong one.',
                'est' => 7,
                'examples' => [
                    [
                        'ref' => '03.23',
                        'title' => 'A string flag that quietly lies',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class AddressManager
                            {
                                private $addresses = ["209.131.36.159", "216.58.213.174"];

                                public function outputAddresses($resolve)
                                {
                                    foreach ($this->addresses as $address) {
                                        print $address;
                                        if ($resolve) {
                                            print " (" . gethostbyaddr($address) . ")";
                                        }
                                        print "\n";
                                    }
                                }
                            }
                            PHP,
                        'why' => 'XML or config values arrive as strings, so "false" passed here resolves to boolean true and every address gets reverse-looked up.',
                        'mistake' => 'Trusting untyped caller data - the string "false" is truthy in PHP.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Types determine how data can be managed', 'Untyped parameters accept anything', 'Config and XML frequently deliver strings where logic expects bool']]],
                    ['paragraph', ['markdown' => 'A flag extracted from `<resolvedomains>false</resolvedomains>` arrives as the string "false". Passed to an untyped `$resolve`, PHP treats it as truthy - the exact opposite of what the document said.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.23']],
                    ['callout', ['text' => 'The fix in the next sections is a type declaration: bool $resolve. With it, the wrong value fails loudly instead of behaving quietly wrong.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Untyped means "anything can arrive"', 'Strings like "false" are truthy', 'Type declarations turn silent bugs into errors']]],
                    ...self::practice(
                        'Reproduce the "false"-is-true bug, then fix it with a bool declaration.',
                        'Why is the string "false" truthy in PHP?',
                        ['type declaration', 'truthy', 'coercion'],
                        'How would you defend typed boundaries in a legacy codebase?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 9: Primitive Types
            [
                'section' => 'Primitive Types',
                'summary' => 'Type declarations for int, float, string and bool - coercion and its pitfalls.',
                'est' => 10,
                'diagrams' => [
                    [
                        'title' => 'Coercive versus strict arguments',
                        'kind' => DiagramKind::Flowchart,
                        'mermaid' => <<<'MMD'
                            flowchart TD
                                A["value passed to typed parameter"] --> B{"declare(strict_types=1) in caller file?"}
                                B -->|no| C["coerced if possible, e.g. '4.22' to float"]
                                B -->|yes| D["TypeError on any mismatch"]
                                E["'false' passed to bool"] --> F["coerces to true, not false"]
                            MMD,
                    ],
                ],
                'examples' => [
                    [
                        'ref' => '03.33',
                        'title' => 'Typed constructor parameters',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $title;
                                public $producerMainName;
                                public $producerFirstName;
                                public $price = 0;

                                public function __construct(
                                    string $title,
                                    string $firstName,
                                    string $mainName,
                                    float $price
                                ) {
                                    $this->title = $title;
                                    $this->producerFirstName = $firstName;
                                    $this->producerMainName = $mainName;
                                    $this->price = $price;
                                }

                                // ...
                            }
                            PHP,
                        'why' => 'The signature now guarantees string, string, string, float for every construction of the class.',
                    ],
                    [
                        'ref' => '03.34',
                        'title' => 'An array where a float is required',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            // will fail
                            $product = new ShopProduct("title", "first", "main", []);
                            PHP,
                        'why' => 'No coercion exists from array to float, so PHP throws a TypeError instead of guessing.',
                        'mistake' => 'Assuming type declarations only warn - an impossible coercion is a fatal TypeError.',
                    ],
                    [
                        'ref' => '03.35',
                        'title' => 'Coercion of a numeric string',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product = new ShopProduct("title", "first", "main", "4.22");
                            PHP,
                        'why' => 'By default PHP converts "4.22" to the float 4.22 behind the scenes - convenient, but it hides sloppy call sites.',
                    ],
                    [
                        'ref' => '03.36',
                        'title' => 'Declaring the flag as bool',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function outputAddresses(bool $resolve)
                            {
                                // ...
                            }
                            PHP,
                        'why' => 'The parameter now demands a real boolean, closing the door on loose strings - but see 03.37.',
                    ],
                    [
                        'ref' => '03.37',
                        'title' => 'The coercion trap returns',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            $manager->outputAddresses("false");
                            PHP,
                        'why' => 'In coercive mode "false" converts to true - functionally identical to passing true, so the bug survives the declaration.',
                        'mistake' => 'Declaring bool and believing you are safe without strict_types - coercive mode still rewrites the value.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['int, float, string, bool are the scalar declarations', 'Coercive mode converts compatible values silently', 'Impossible coercions raise TypeError']]],
                    ['paragraph', ['markdown' => 'Type declarations on constructor and method parameters make the class contract explicit: callers know what to pass, and PHP enforces it. The remaining question is how forgiving PHP should be about near-misses.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'Coercive versus strict arguments']],
                    ['code_example', ['_ref' => '03.33']],
                    ['code_example', ['_ref' => '03.34']],
                    ['code_example', ['_ref' => '03.35']],
                    ['code_example', ['_ref' => '03.36']],
                    ['code_example', ['_ref' => '03.37']],
                    ['callout', ['text' => 'declare(strict_types=1) must sit at the top of the calling file, not the file where the method is defined - strictness follows the call site.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Declarations document and enforce input', 'Coercion is the default, strict is opt-in per file', 'Booleans are the classic coercion trap']]],
                    ...self::practice(
                        'Type every ShopProduct constructor parameter, then break it three different ways.',
                        'What does coercive mode do with the string "4.22" for a float parameter?',
                        ['scalar type', 'TypeError', 'strict_types'],
                        'Where should strict_types live and why is that surprising?'
                    ),
                ],
            ],
        ];
    }

    /**
     * Sections 10-14: Type-checking through Union Types.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsThree(): array
    {
        return [
            // ------------------------------------------------------ 10: Some Other Type-Checking Functions
            [
                'section' => 'Some Other Type-Checking Functions',
                'summary' => 'is_int(), is_string() and friends for runtime type checks.',
                'est' => 6,
                'examples' => [],
                'blocks' => [
                    ['bullets', ['items' => ['is_int(), is_float(), is_string(), is_bool() check scalars', 'is_array(), is_object() cover the rest', 'gettype() returns the type as a string']]],
                    ['paragraph', ['markdown' => 'Before declarations were this capable, every method body started with manual checks. They still have a place: data arriving from JSON, databases or user input is untyped until you inspect it.']],
                    ['book_quote', []],
                    ['table', ['headers' => ['Function', 'Returns true for'], 'rows' => [['is_int($v)', 'integer'], ['is_float($v)', 'float'], ['is_string($v)', 'string'], ['is_bool($v)', 'bool'], ['is_array($v)', 'array'], ['is_object($v)', 'object'], ['is_null($v)', 'null']]]],
                    ['callout', ['text' => 'Prefer a type declaration when the check describes an argument or return value - use is_*() when you must branch on a value whose type is genuinely open.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['is_* returns bool, it does not throw', 'They validate values, not contracts', 'Declarations enforce; predicates branch']]],
                    ...self::practice(
                        'Write a normalise($v) function that branches with is_int/is_string/is_array.',
                        'What is the difference between a type declaration and is_int()?',
                        ['is_int', 'gettype', 'runtime check'],
                        'When does a runtime check beat a declaration, if ever?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 11: Type Declarations: Object Types
            [
                'section' => 'Type Declarations: Object Types',
                'summary' => 'Require a specific class or interface in a signature.',
                'est' => 7,
                'examples' => [
                    [
                        'ref' => '03.28',
                        'title' => 'A writer that accepts any value',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProductWriter
                            {
                                public function write($shopProduct)
                                {
                                    $str = $shopProduct->title . ": "
                                        . $shopProduct->getProducer()
                                        . " ({$shopProduct->price})\n";
                                    print $str;
                                }
                            }
                            PHP,
                        'why' => 'Untyped, this method explodes on anything without title/getProducer - and nothing warns the caller.',
                    ],
                    [
                        'ref' => '03.29',
                        'title' => 'Calling the writer',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product1 = new ShopProduct("My Antonia", "Willa", "Cather", 5.99);
                            $writer = new ShopProductWriter();
                            $writer->write($product1);
                            PHP,
                        'output' => 'My Antonia: Willa Cather (5.99)',
                        'why' => 'Works as long as the argument really is a ShopProduct - which a ShopProduct type declaration would now guarantee.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['An object type declaration names a class or interface', 'Anything else fails with TypeError', 'It documents the expected collaborator']]],
                    ['paragraph', ['markdown' => 'Declaring `ShopProduct $shopProduct` says: this method only works with that design. Client code gets the requirement in the signature, and PHP rejects mistakes before the body runs.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.28']],
                    ['code_example', ['_ref' => '03.29']],
                    ['callout', ['text' => 'Object declarations are invariant: ShopProduct does not accept a subclass unless you widen to an interface or a parent type - pick the narrowest type that still describes the need.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['class names in signatures enforce identity of type', 'Interfaces give you flexibility', 'The declaration replaces defensive if-logic']]],
                    ...self::practice(
                        'Type ShopProductWriter::write() and confirm a wrong argument raises TypeError.',
                        'What error does a mismatched object argument produce?',
                        ['object type', 'TypeError', 'collaborator'],
                        'Interface or concrete class in a signature: how do you choose?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 12: Type Declarations: Primitive Types
            [
                'section' => 'Type Declarations: Primitive Types',
                'summary' => 'Optional typed parameters, defaults, and file-level strict typing.',
                'est' => 8,
                'examples' => [
                    [
                        'ref' => '03.39',
                        'title' => 'Optional array parameter with a default',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ConfReader
                            {
                                public function getValues(array $default = [])
                                {
                                    $values = [];

                                    // do something to get values

                                    // merge the provided defaults (it will always be an array)
                                    $values = array_merge($default, $values);
                                    return $values;
                                }
                            }
                            PHP,
                        'why' => 'A default plus a type gives you an optional argument that is still constrained whenever a value is supplied.',
                        'syntax' => 'Type before the parameter name, default after: array $default = [].',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Defaults and types combine cleanly', 'The default itself must satisfy the declared type', 'strict_types applies to the calling file']]],
                    ['paragraph', ['markdown' => 'You already met coercion in the Primitive Types section. This pass focuses on the mechanics: optional typed parameters, what defaults may contain, and how declare(strict_types=1) changes the bargain.']],
                    ['book_quote', []],
                    ['tabs', ['tabs' => [['label' => 'Coercive (default)', 'content' => 'PHP converts where it can: "4.22" becomes float 4.22; "false" becomes true. Convenient, occasionally misleading.'], ['label' => 'strict_types=1', 'content' => 'The calling file opts in. Any value that is not already the declared type raises TypeError - no silent rewrites.']]]],
                    ['code_example', ['_ref' => '03.39']],
                    ['callout', ['text' => 'declare(strict_types=1); must be the very first statement of the caller\'s file. It never affects code inside the declared function\'s own file.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Optional but still typed', 'Strictness is chosen per calling file', 'Both modes raise TypeError on impossible values']]],
                    ...self::practice(
                        'Add strict_types to a script and list which of your existing calls start failing.',
                        'Where must declare(strict_types=1) appear?',
                        ['strict_types', 'optional parameter'],
                        'Should a library author or its callers decide on strict typing? Why?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 13: mixed Types
            [
                'section' => 'mixed Types',
                'summary' => 'Declare that any type is accepted - on purpose.',
                'est' => 5,
                'examples' => [
                    [
                        'ref' => '03.41',
                        'title' => 'An explicitly any-typed parameter',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class Storage
                            {
                                public function add(string $key, mixed $value)
                                {
                                    // do something with $key and $value
                                }
                            }
                            PHP,
                        'why' => 'mixed documents that the value type is genuinely open - same runtime as no declaration, but the intent is written down.',
                        'syntax' => 'mixed accepts array, bool, callable, int, float, null, object, resource or string.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['mixed = any type, including null', 'Runtime-equivalent to no declaration', 'Signals intentional openness, not laziness']]],
                    ['paragraph', ['markdown' => 'A bare `$value` might mean "anything" or "the author could not be bothered". `mixed $value` says the openness is a decision - which is exactly what a signature should communicate.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.41']],
                    ['callout', ['text' => 'mixed is not an excuse to skip typing. If the value only ever needs string or int, say so; mixed removes every guarantee the parameter could have given.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['Use when the domain is truly heterogeneous', 'Different from untyped only in intent', 'Always pair with clear documentation']]],
                    ...self::practice(
                        'Audit three untyped parameters: which are mixed on purpose, which are just unchecked?',
                        'What types does mixed accept?',
                        ['mixed', 'type intent'],
                        'What is the practical difference between mixed and no declaration?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 14: Union Types
            [
                'section' => 'Union Types',
                'summary' => 'Accept any of several types with PHP 8 union declarations.',
                'est' => 9,
                'examples' => [
                    [
                        'ref' => '03.42',
                        'title' => 'The manual check unions replace',
                        'tier' => CodeTier::RealWorld,
                        'code' => <<<'PHP'
                            class Storage
                            {
                                public function add(string $key, $value)
                                {
                                    if (! is_bool($value) && ! is_string($value)) {
                                        error_log("value must be string or Boolean - given: " .
                                    gettype($value));
                                        return false;
                                    }
                                    // do something with $key and $value
                                }
                            }
                            PHP,
                        'why' => 'Unwieldy and easy to forget - the union declaration below states the same contract in the signature where callers can see it.',
                    ],
                    [
                        'ref' => '03.43',
                        'title' => 'string|bool in a signature',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class Storage
                            {
                                public function add(string $key, string|bool $value)
                                {
                                    // do something with $key and $value
                                }
                            }
                            PHP,
                        'why' => 'Anything outside the union raises TypeError; no manual branch, no silent false returns.',
                    ],
                    [
                        'ref' => '03.44',
                        'title' => 'Adding null to the union',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class Storage
                            {
                                public function add(string $key, string|bool|null $value)
                                {
                                    // do something with $key and $value
                                }
                            }
                            PHP,
                        'why' => 'null becomes an explicitly permitted value instead of a lucky side effect of untyped parameters.',
                    ],
                    [
                        'ref' => '03.45',
                        'title' => 'A union of object and null',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function setShopProduct(ShopProduct|null $product)
                            {
                                // do something with $product
                            }
                            PHP,
                        'why' => 'Object types compose into unions too - this is exactly what ?ShopProduct means, spelled out.',
                    ],
                    [
                        'ref' => '03.46',
                        'title' => 'The false pseudo-type',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function setShopProduct2(ShopProduct|false $product)
                            {
                                // do something with $product
                            }
                            PHP,
                        'why' => 'ShopProduct|false admits failure without admitting true - ShopProduct|bool would be wider than intended.',
                        'syntax' => 'false is only valid inside a union, never as a standalone declaration.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['PHP 8 unions accept any listed type', 'They replace is_* validation blocks', 'false and null have special rules']]],
                    ['paragraph', ['markdown' => 'The manual `is_bool() && is_string()` dance in listing 03.42 does in twelve lines what `string|bool $value` does in one - and the declaration fails loudly instead of returning false quietly.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.42']],
                    ['code_example', ['_ref' => '03.43']],
                    ['code_example', ['_ref' => '03.44']],
                    ['code_example', ['_ref' => '03.45']],
                    ['code_example', ['_ref' => '03.46']],
                    ['modern_panel', ['book' => 'Before PHP 8 you declared no type and validated with is_*() inside the body, returning false or throwing by hand.', 'modern' => 'Unions move that contract into the signature: string|bool|null, ShopProduct|false, int|float. PHP 8.2 adds the DNF forms and readonly classes around the same idea.', 'why' => 'Signature-level contracts fail at the call boundary - the cheapest possible moment to fail.']],
                    ['callout', ['text' => 'PHP 8 supports the false pseudo-type only within unions: ShopProduct|false is legal, plain false as an argument type is not (until standalone false arrived as return-only). Prefer explicit false|... unions over bool.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['A|B means A or B', 'null|T is the long form of ?T', 'Use false when true would be wrong']]],
                    ...self::practice(
                        'Convert three is_* validation blocks into union declarations.',
                        'Why prefer ShopProduct|false over ShopProduct|bool?',
                        ['union type', 'false pseudo-type', 'nullable union'],
                        'Where did you last see defensive type checks that a union could replace?'
                    ),
                ],
            ],
        ];
    }

    /**
     * Sections 15-18: Nullable through The Inheritance Problem.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsFour(): array
    {
        return [
            // ------------------------------------------------------ 15: Nullable Types
            [
                'section' => 'Nullable Types',
                'summary' => 'Accept null deliberately with the ?Type shorthand.',
                'est' => 5,
                'examples' => [],
                'blocks' => [
                    ['bullets', ['items' => ['?Type means Type|null', 'Nullability must be explicit', 'Works for arguments, returns and properties']]],
                    ['paragraph', ['markdown' => 'Untyped parameters accepted null by accident; typed ones reject it unless you ask. `?ShopProduct $product` is the shorthand for `ShopProduct|null $product` - one character that documents "absent is a real state here".']],
                    ['book_quote', []],
                    ['table', ['headers' => ['Declaration', 'Accepts'], 'rows' => [['ShopProduct $p', 'ShopProduct only'], ['?ShopProduct $p', 'ShopProduct or null'], ['ShopProduct|null $p', 'identical to ?ShopProduct']]]],
                    ['callout', ['text' => 'The ? shorthand must come first and only one type follows: ?ShopProduct is fine, string|?bool is not - write string|bool|null instead.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['?T is sugar for T|null', 'Default null does not imply nullable', 'Applies to properties too']]],
                    ...self::practice(
                        'Mark one collaborator nullable and handle the absence case explicitly.',
                        'What does ?ShopProduct mean?',
                        ['nullable type', 'null object'],
                        'Is null ever the right design, or should you use a special object?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 16: Return Type Declarations
            [
                'section' => 'Return Type Declarations',
                'summary' => 'Enforce what a method hands back, including void and unions.',
                'est' => 8,
                'examples' => [
                    [
                        'ref' => '03.48',
                        'title' => 'An int return type',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function getPlayLength(): int
                            {
                                return $this->playLength;
                            }
                            PHP,
                        'why' => 'Callers may treat the result as an integer with assurance; a missing or wrong return raises TypeError instead of null leaking out.',
                        'mistake' => 'Forgetting to return - PHP enforces the declaration and errors with "none returned".',
                    ],
                    [
                        'ref' => '03.49',
                        'title' => 'A union return type',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function getPrice(): int|float
                            {
                                return ($this->price - $this->discount);
                            }
                            PHP,
                        'why' => 'Price arithmetic can land on either numeric type, so the declaration admits both without going mixed.',
                    ],
                    [
                        'ref' => '03.50',
                        'title' => 'The void return type',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            public function setDiscount(int|float $num): void
                            {
                                $this->discount = $num;
                            }
                            PHP,
                        'why' => 'void says this method exists to mutate, not to answer - a return statement with a value would be an error.',
                        'syntax' => 'void is return-only; you may not use it for arguments.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['The colon after ) declares the return type', 'Wrong or missing returns raise TypeError', 'void, unions and nullables all work here']]],
                    ['paragraph', ['markdown' => 'Return declarations let callers trust the shape of what comes back. If getPlayLength() promises int, downstream code can do arithmetic without is_int() padding.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.48']],
                    ['code_example', ['_ref' => '03.49']],
                    ['code_example', ['_ref' => '03.50']],
                    ['callout', ['text' => 'A function with no return statement at all satisfies void but fails every other declaration. Mixed up your returns and declarations? PHP tells you at the moment the method exits, not later.', 'variant' => 'tip']],
                    ['bullets', ['items' => [': T enforces the contract on exit', 'void forbids returning values', 'Unions model real-world results']]],
                    ...self::practice(
                        'Add return types to every method in your chapter 3 classes.',
                        'When does PHP raise an error for a declared return type?',
                        ['return type', 'void', 'TypeError'],
                        'Why is void useful documentation even though PHP could infer it?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 17: Inheritance
            [
                'section' => 'Inheritance',
                'summary' => 'Derive classes from a base class and reuse what it already does.',
                'est' => 7,
                'diagrams' => [
                    [
                        'title' => 'Parent and child classes',
                        'kind' => DiagramKind::ClassDiagram,
                        'mermaid' => <<<'MMD'
                            classDiagram
                                class ShopProduct {
                                    +string title
                                    +float price
                                    +__construct(title, firstName, mainName, price)
                                    +getProducer() string
                                }
                                class BookProduct {
                                    +int numPages
                                    +getNumberOfPages() int
                                }
                                class CdProduct {
                                    +int playLength
                                    +getPlayLength() int
                                }
                                ShopProduct <|-- BookProduct
                                ShopProduct <|-- CdProduct
                            MMD,
                    ],
                ],
                'examples' => [],
                'blocks' => [
                    ['bullets', ['items' => ['A subclass derives from a base class', 'It inherits properties and methods', 'It may extend or override behaviour']]],
                    ['paragraph', ['markdown' => 'A class that inherits is a subclass; the one it inherits from is its superclass. Child extends parent - adding new functionality and, where needed, replacing what the parent already did.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'Parent and child classes']],
                    ['paragraph', ['markdown' => 'The book deliberately delays the syntax: first understand the problem inheritance solves (next lesson), then the extends keyword makes sense as a solution rather than a trick.']],
                    ['callout', ['text' => 'Child classes inherit public and protected members. Private members exist in the parent but are invisible to the child - a distinction that trips up almost everyone once.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['subclass / superclass = child / parent', 'extends expresses the relationship', 'Inheritance is a last resort - prefer composition where you can']]],
                    ...self::practice(
                        'Draw one parent and two children for a domain you know, marking shared versus specific members.',
                        'Which members does a child class inherit?',
                        ['inheritance', 'subclass', 'extends'],
                        'What is the difference between inheriting and overriding?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 18: The Inheritance Problem
            [
                'section' => 'The Inheritance Problem',
                'summary' => 'See how one class trying to be two types collapses under its own weight.',
                'est' => 9,
                'examples' => [
                    [
                        'ref' => '03.52',
                        'title' => 'One class trying to be two',
                        'tier' => CodeTier::RealWorld,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $numPages;
                                public $playLength;
                                public $title;
                                public $producerMainName;
                                public $producerFirstName;
                                public $price;

                                public function __construct(
                                    string $title,
                                    string $firstName,
                                    string $mainName,
                                    float $price,
                                    int $numPages = 0,
                                    int $playLength = 0
                                ) {
                                    $this->title = $title;
                                    $this->producerFirstName = $firstName;
                                    $this->producerMainName = $mainName;
                                    $this->price = $price;
                                    $this->numPages = $numPages;
                                    $this->playLength = $playLength;
                                }

                                public function getNumberOfPages(): int
                                {
                                    return $this->numPages;
                                }

                                public function getPlayLength(): int
                                {
                                    return $this->playLength;
                                }
                            }
                            PHP,
                        'why' => 'Both formats live in one class, so every constructor call must supply fields that only make sense for one of them.',
                        'mistake' => 'Letting convenience arguments grow until one type serves two (or more) domains.',
                    ],
                    [
                        'ref' => '03.53',
                        'title' => 'Behaviour branching on type',
                        'tier' => CodeTier::RealWorld,
                        'code' => <<<'PHP'
                            public function getSummaryLine(): string
                            {
                                $base = "{$this->title} ( {$this->producerMainName}, ";
                                $base .= "{$this->producerFirstName} )";
                                if ($this->type == 'book') {
                                    $base .= ": page count - {$this->numPages}";
                                } elseif ($this->type == 'cd') {
                                    $base .= ": playing time - {$this->playLength}";
                                }
                                return $base;
                            }
                            PHP,
                        'why' => 'Every new format means another branch - and the summary logic now knows about every product type in the system.',
                    ],
                    [
                        'ref' => '03.56',
                        'title' => 'Type-checking in client code',
                        'tier' => CodeTier::RealWorld,
                        'code' => <<<'PHP'
                            class ShopProductWriter
                            {
                                public function write($shopProduct): void
                                {
                                    if (
                                        ! ($shopProduct instanceof CdProduct) &&
                                        ! ($shopProduct instanceof BookProduct)
                                    ) {
                                        die("wrong type supplied");
                                    }
                                    $str = "{$shopProduct->title}: "
                                        . $shopProduct->getProducer()
                                        . " ({$shopProduct->price})\n";
                                    print $str;
                                }
                            }
                            PHP,
                        'why' => 'instanceof restores a little safety, but the writer must be edited for every new product type - the opposite of open for extension.',
                        'syntax' => 'instanceof evaluates to bool and accepts a class or interface name on the right.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Type fields and if-chains signal a missing abstraction', 'Each new variant taxes every branch', 'Client code starts policing types']]],
                    ['paragraph', ['markdown' => 'Add a second format to ShopProduct and the problems compound: a $type field, branches in getSummaryLine(), instanceof checks in the writer. The class is becoming two classes wearing one coat.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.52']],
                    ['code_example', ['_ref' => '03.53']],
                    ['code_example', ['_ref' => '03.56']],
                    ['callout', ['text' => 'Smell test: if adding a variant means editing every if/elseif and every instanceof, your types are expressed in data instead of in the class hierarchy.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['The problem motivates inheritance', 'Splitting into BookProduct and CdProduct removes the branches', 'Overriding replaces per-type conditionals']]],
                    ...self::practice(
                        'Refactor a type-switch into two classes with one shared parent.',
                        'Why are instanceof chains a design smell?',
                        ['type field', 'instanceof', 'single responsibility'],
                        'When is a simple type field actually fine?'
                    ),
                ],
            ],
        ];
    }

    /**
     * Sections 19-22: Working with Inheritance through Summary.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsFive(): array
    {
        return [
            // ------------------------------------------------------ 19: Working with Inheritance
            [
                'section' => 'Working with Inheritance',
                'summary' => 'extends, parent:: and method overriding in practice.',
                'est' => 10,
                'examples' => [
                    [
                        'ref' => '03.58',
                        'title' => 'A child class that extends and overrides',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class CdProduct extends ShopProduct
                            {
                                public function getPlayLength(): int
                                {
                                    return $this->playLength;
                                }

                                public function getSummaryLine(): string
                                {
                                    $base = "{$this->title} ( {$this->producerMainName}, ";
                                    $base .= "{$this->producerFirstName} )";
                                    $base .= ": playing time - {$this->playLength}";
                                    return $base;
                                }
                            }
                            PHP,
                        'why' => 'CdProduct inherits everything common from ShopProduct and replaces only the summary that differs.',
                    ],
                    [
                        'ref' => '03.59',
                        'title' => 'The book-flavoured sibling',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class BookProduct extends ShopProduct
                            {
                                public function getNumberOfPages(): int
                                {
                                    return $this->numPages;
                                }

                                public function getSummaryLine(): string
                                {
                                    $base = "{$this->title} ( {$this->producerMainName}, ";
                                    $base .= "{$this->producerFirstName} )";
                                    $base .= ": page count - {$this->numPages}";
                                    return $base;
                                }
                            }
                            PHP,
                        'why' => 'Both children override getSummaryLine() with their own rule - the type if-chain from 03.53 is gone.',
                    ],
                    [
                        'ref' => '03.60',
                        'title' => 'Using child objects through the parent API',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $product2 = new CdProduct(
                                "Exile on Coldharbour Lane",
                                "The",
                                "Alabama 3",
                                10.99,
                                0,
                                60.33
                            );
                            print "artist: {$product2->getProducer()}\n";
                            PHP,
                        'output' => 'artist: The Alabama 3',
                        'why' => 'getProducer() lives in the parent, yet works unchanged on the child - and ShopProductWriter can accept either child.',
                    ],
                    [
                        'ref' => '03.61',
                        'title' => 'Parent constructor called from children',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProduct
                            {
                                public $title;
                                public $producerMainName;
                                public $producerFirstName;
                                public $price;

                                public function __construct(
                                    $title,
                                    $firstName,
                                    $mainName,
                                    $price
                                ) {
                                    $this->title = $title;
                                    $this->producerFirstName = $firstName;
                                    $this->producerMainName = $mainName;
                                    $this->price = $price;
                                }

                                public function getProducer(): string
                                {
                                    return $this->producerFirstName . " "
                                        . $this->producerMainName;
                                }

                                public function getSummaryLine(): string
                                {
                                    $base = "{$this->title} ( {$this->producerMainName}, ";
                                    $base .= "{$this->producerFirstName} )";
                                    return $base;
                                }
                            }
                            PHP,
                        'why' => 'The parent keeps shared setup and a base summary; children add specifics with parent::getSummaryLine() where useful.',
                        'syntax' => 'parent::__construct(...) inside a child constructor runs the inherited setup exactly once.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['extends creates the child', 'Children override methods by redeclaring them', 'parent:: reaches the superclass implementation']]],
                    ['paragraph', ['markdown' => 'Now the type-switch problem has a structural fix: each format becomes its own class, sharing what is common and overriding what differs. The writer no longer needs instanceof - it asks for ShopProduct and gets any child.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.58']],
                    ['code_example', ['_ref' => '03.59']],
                    ['code_example', ['_ref' => '03.60']],
                    ['code_example', ['_ref' => '03.61']],
                    ['callout', ['text' => 'If a child defines no constructor, the parent\'s runs automatically. Once a child declares its own __construct(), it must call parent::__construct() explicitly or the shared setup is skipped.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['One shared parent, several focused children', 'Overrides specialise behaviour', 'Call parent::__construct() when you add your own']]],
                    ...self::practice(
                        'Split ShopProduct into BookProduct and CdProduct, then print both summary lines.',
                        'When does a child automatically run the parent constructor?',
                        ['extends', 'method override', 'parent::'],
                        'How does inheritance remove the instanceof chain from the writer?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 20: Public, Private, and Protected
            [
                'section' => 'Public, Private, and Protected',
                'summary' => 'Control who can see and change what with visibility keywords.',
                'est' => 9,
                'diagrams' => [
                    [
                        'title' => 'Visibility at a glance',
                        'kind' => DiagramKind::Flowchart,
                        'mermaid' => <<<'MMD'
                            flowchart TD
                                A["public"] --> B["any code may read and write"]
                                C["protected"] --> D["this class and its subclasses only"]
                                E["private"] --> F["the declaring class only"]
                                G["client code"] --> B
                                G -->|"direct access"| H["blocked for protected and private"]
                            MMD,
                    ],
                ],
                'examples' => [
                    [
                        'ref' => '03.70',
                        'title' => 'A private collection property',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class ShopProductWriter
                            {
                                private $products = [];

                                //...
                            }
                            PHP,
                        'why' => 'External code can no longer overwrite the array wholesale; everything must flow through addProduct(), which type-checks each entry.',
                        'syntax' => 'As far as the rest of the world is concerned, a private property has ceased to exist.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['public: visible everywhere', 'protected: class + subclasses', 'private: declaring class only']]],
                    ['paragraph', ['markdown' => 'Visibility is the tool for keeping promises. Want the adjusted price to be the only price clients see? Make $price private and expose getPrice() - direct reads then fail outright.']],
                    ['book_quote', []],
                    ['diagram', ['_ref' => 'Visibility at a glance']],
                    ['code_example', ['_ref' => '03.70']],
                    ['table', ['headers' => ['Keyword', 'Class', 'Subclass', 'Outside'], 'rows' => [['public', 'yes', 'yes', 'yes'], ['protected', 'yes', 'yes', 'no'], ['private', 'yes', 'no', 'no']]]],
                    ['callout', ['text' => 'Default visibility is public when you write nothing - but PSR-12 requires the keyword on every property and method, so the contract is always visible in the source.', 'variant' => 'tip']],
                    ['bullets', ['items' => ['Hide state, expose behaviour', 'Private forces access through methods', 'Protected is a contract with future children']]],
                    ...self::practice(
                        'Make ShopProduct\'s price private and prove external reads now fail.',
                        'Who can read a private property?',
                        ['public', 'private', 'protected'],
                        'Why does making members private usually improve, not weaken, an API?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 21: Typed Properties
            [
                'section' => 'Typed Properties',
                'summary' => 'Put type guarantees on properties themselves, not just on method signatures.',
                'est' => 7,
                'examples' => [
                    [
                        'ref' => '03.72',
                        'title' => 'Typed public properties',
                        'tier' => CodeTier::Core,
                        'code' => <<<'PHP'
                            class Point
                            {
                                public int $x = 0;
                                public int $y = 0;
                            }
                            PHP,
                        'why' => 'Even fully public, Point guarantees integers - getters, setters and setVals() become optional decoration.',
                    ],
                    [
                        'ref' => '03.73',
                        'title' => 'Assigning the wrong type',
                        'tier' => CodeTier::Starter,
                        'code' => <<<'PHP'
                            $point = new Point();
                            $point->x = "a";
                            PHP,
                        'output' => 'TypeError: Cannot assign string to property Point::$x of type int',
                        'why' => 'The engine rejects the assignment itself - the guarantee now lives at the property, not at each method that touches it.',
                        'mistake' => 'Leaving a typed property without a default: reading it before first assignment is an "must not be accessed before initialization" error.',
                    ],
                ],
                'blocks' => [
                    ['bullets', ['items' => ['Properties can carry type declarations since PHP 7.4', 'Wrong assignments fail immediately', 'Uninitialized typed properties error on read']]],
                    ['paragraph', ['markdown' => 'Before typed properties, integer-only fields needed private storage plus setter validation. Now the type on the property itself does the work - simple classes stay simple without giving up guarantees.']],
                    ['book_quote', []],
                    ['code_example', ['_ref' => '03.72']],
                    ['code_example', ['_ref' => '03.73']],
                    ['modern_panel', ['book' => 'The book first shows the classic pattern: private properties guarded by setVals() and getters, with validation logic in the methods.', 'modern' => 'Typed properties (7.4+) let public int $x = 0 stand on its own; PHP 8.1 readonly and PHP 8.4 property hooks build further on the same declaration point.', 'why' => 'The guarantee belongs with the data, so every code path benefits without calling a validating method.']],
                    ['callout', ['text' => 'A typed property with no default is uninitialized, not null: reading it throws. Either give it a default or set it in the constructor.', 'variant' => 'warning']],
                    ['bullets', ['items' => ['Type lives on the declaration', 'Assignments are validated by PHP', 'Combine with promotion for one-line fields']]],
                    ...self::practice(
                        'Type your class properties, then try assigning each wrong kind of value.',
                        'What happens when you assign a string to a typed int property?',
                        ['typed property', 'uninitialized', 'readonly'],
                        'Typed public versus private with getters: when does each earn its keep?'
                    ),
                ],
            ],

            // ------------------------------------------------------ 22: Summary
            [
                'section' => 'Summary',
                'summary' => 'The chapter in one pass: classes, types, inheritance and visibility.',
                'est' => 4,
                'examples' => [],
                'blocks' => [
                    ['bullets', ['items' => ['Classes define types; objects are instances created with new', 'Constructors (with promotion) initialise state safely', 'Type declarations on arguments, returns and properties enforce contracts', 'Unions, nullables and mixed widen declarations deliberately', 'Inheritance (extends) replaces type branches with specialised classes', 'Visibility (public/protected/private) keeps state behind behaviour']]],
                    ['paragraph', ['markdown' => 'Everything in this chapter served one goal: making the shape of your data explicit and enforced. The type system does the paperwork, inheritance does the reuse, and visibility decides who gets to change what.']],
                    ['book_quote', []],
                    ['callout', ['text' => 'Next up: Chapter 4 uses these same tools to build in-depth classes with abstract types, interfaces, traits and exceptions.', 'variant' => 'tip']],
                    ...self::practice(
                        'Rebuild ShopProduct from scratch using everything from this chapter in one file.',
                        'Which chapter tool enforces data shape at assignment time?',
                        ['class', 'constructor', 'type declaration', 'inheritance', 'visibility'],
                        'Walk a teammate through how this chapter\'s pieces fit together in five minutes.'
                    ),
                ],
            ],
        ];
    }
}
