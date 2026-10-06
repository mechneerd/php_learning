<?php

namespace Database\Seeders;

use App\Enums\CardType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Concept;
use App\Models\Flashcard;
use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Flashcards for the Stage 0 + Chapter 3 seed scope (docs/15 phase 4, ~80 rows).
 *
 * Part A derives one definition card per concept linked to a Chapter 3
 * lesson (front = term, back = the concept definition from the knowledge
 * map), keeping concept_id and the lesson citation. Part B adds hand-authored
 * syntax, difference and snippet cards across the chapter's topics.
 *
 * Reviews are not seeded: the SM-2 lite scheduler (docs/04 section 7)
 * starts every card at first exposure when a learner meets it.
 */
final class FlashcardSeeder extends Seeder
{
    /**
     * @var list<array{type: string, concept: string|null, front: string, back: string}>
     */
    private const CARDS = [
        // --- Syntax cards ---
        ['type' => 'syntax', 'concept' => 'class', 'front' => 'Class declaration syntax', 'back' => "class Name\n{\n    // properties and methods\n}"],
        ['type' => 'syntax', 'concept' => 'class', 'front' => 'Instantiating an object', 'back' => '$obj = new Name;  // or new Name($args)'],
        ['type' => 'syntax', 'concept' => 'constructor', 'front' => 'Constructor syntax', 'back' => "public function __construct(string \$name)\n{\n    \$this->name = \$name;\n}"],
        ['type' => 'syntax', 'concept' => 'constructor_promotion', 'front' => 'Constructor property promotion syntax', 'back' => "public function __construct(\n    private string \$name,\n    public int \$age = 0,\n) {}"],
        ['type' => 'syntax', 'concept' => 'properties', 'front' => 'Property declaration with a default', 'back' => 'private int $retries = 3;'],
        ['type' => 'syntax', 'concept' => 'properties', 'front' => 'Typed property, no default', 'back' => 'public string $title;  // uninitialized until set - reading early throws Error'],
        ['type' => 'syntax', 'concept' => 'properties', 'front' => 'Calling a method on an object', 'back' => '$result = $obj->method($arg);'],
        ['type' => 'syntax', 'concept' => 'visibility', 'front' => 'The three visibility keywords', 'back' => "public    // everywhere\nprotected // class + subclasses\nprivate   // declaring class only"],
        ['type' => 'syntax', 'concept' => 'type_declarations', 'front' => 'Parameter and return types', 'back' => 'function area(int $w, int $h): int { return $w * $h; }'],
        ['type' => 'syntax', 'concept' => 'type_declarations', 'front' => 'Object type declaration', 'back' => 'function book(Appointment $slot): string { ... }'],
        ['type' => 'syntax', 'concept' => 'union_types', 'front' => 'Union type syntax', 'back' => 'function id(int|string $v): int|string { return $v; }'],
        ['type' => 'syntax', 'concept' => 'nullable_types', 'front' => 'Nullable shorthand', 'back' => 'function find(int $id): ?array { ... }   // = int|array-ish with null allowed'],
        ['type' => 'syntax', 'concept' => 'mixed', 'front' => 'mixed type syntax', 'back' => 'function dump(mixed $value): void { var_dump($value); }'],
        ['type' => 'syntax', 'concept' => 'type_declarations', 'front' => 'void return type', 'back' => 'function log(string $msg): void { ... }'],
        ['type' => 'syntax', 'concept' => 'type_coercion', 'front' => 'Enabling strict types', 'back' => 'declare(strict_types=1);   // first statement of the file'],
        ['type' => 'syntax', 'concept' => 'named_arguments', 'front' => 'Named argument call', 'back' => 'f(b: 10, a: 3);   // order no longer matters'],
        ['type' => 'syntax', 'concept' => 'functions', 'front' => 'Default argument', 'back' => "function greet(string \$name, string \$greeting = 'Hi') { ... }"],
        ['type' => 'syntax', 'concept' => 'inheritance', 'front' => 'Extending a class', 'back' => 'class Dog extends Animal { ... }'],
        ['type' => 'syntax', 'concept' => 'inheritance', 'front' => 'Overriding and calling the parent', 'back' => "public function speak(): string\n{\n    return parent::speak().' Woof';\n}"],
        ['type' => 'syntax', 'concept' => 'inheritance', 'front' => 'Constructor that runs the parent constructor', 'back' => "public function __construct(string \$name)\n{\n    parent::__construct(\$name);\n}"],
        ['type' => 'syntax', 'concept' => 'encapsulation', 'front' => 'Getter pattern for a private property', 'back' => "private int \$balance = 0;\n\npublic function balance(): int\n{\n    return \$this->balance;\n}"],
        ['type' => 'syntax', 'concept' => 'type_checking', 'front' => 'Type-checking functions', 'back' => 'is_int(), is_string(), is_array(), is_numeric(), is_object() - they report, never convert.'],
        ['type' => 'syntax', 'concept' => 'class', 'front' => 'Class constants', 'back' => "class Limits\n{\n    public const MAX = 10;\n}\necho Limits::MAX;"],
        ['type' => 'syntax', 'concept' => 'object_identity', 'front' => 'Identity comparison of objects', 'back' => '$a === $b   // true only when both hold the same instance'],

        // --- Difference cards ---
        ['type' => 'difference', 'concept' => 'class', 'front' => 'class vs object', 'back' => 'A class is the declared blueprint; an object is a concrete instance of it created with new.'],
        ['type' => 'difference', 'concept' => 'visibility', 'front' => 'private vs protected vs public', 'back' => 'private: declaring class only. protected: class + subclasses. public: everywhere.'],
        ['type' => 'difference', 'concept' => 'object_identity', 'front' => '== vs === for objects', 'back' => '== compares properties loosely (same shape can be equal); === compares identity - the same instance.'],
        ['type' => 'difference', 'concept' => 'functions', 'front' => 'argument vs parameter', 'back' => 'The parameter is the variable in the signature; the argument is the value passed at the call site.'],
        ['type' => 'difference', 'concept' => 'type_coercion', 'front' => 'coercive vs strict typing', 'back' => 'Default: PHP converts compatible values (coercion). With declare(strict_types=1) mismatches throw TypeError.'],
        ['type' => 'difference', 'concept' => 'union_types', 'front' => 'union vs nullable', 'back' => '?int is int|null shorthand. A union lists any set: int|string. Nullable is the two-item case.'],
        ['type' => 'difference', 'concept' => 'constructor_promotion', 'front' => 'promotion vs manual assignment', 'back' => 'Promotion declares the property from the constructor parameter itself; manual style declares the property and assigns $this->x = $x.'],
        ['type' => 'difference', 'concept' => 'typed_properties', 'front' => 'typed property vs parameter type', 'back' => 'Both enforce at assignment/call time. The property type guards stored state; the parameter type guards the call boundary.'],
        ['type' => 'difference', 'concept' => 'encapsulation', 'front' => 'public property vs private + getter', 'back' => 'Public property: anyone can write it anytime. Private + getter: reads are controlled and writes can be validated in one place.'],
        ['type' => 'difference', 'concept' => 'inheritance', 'front' => 'inherit vs override', 'back' => 'Inherit: the child receives parent members unchanged. Override: the child redefines a member with a compatible signature.'],
        ['type' => 'difference', 'concept' => 'properties', 'front' => 'method vs function', 'back' => 'A function lives in global scope; a method belongs to a class and is called on an object (or statically on a class).'],
        ['type' => 'difference', 'concept' => 'properties', 'front' => 'property vs variable', 'back' => 'A variable is scoped to a function/block; a property is part of every object of a class.'],
        ['type' => 'difference', 'concept' => 'named_arguments', 'front' => 'positional vs named arguments', 'back' => 'Positional: order matters. Named: each value binds to its parameter by name, so optional arguments can be skipped safely.'],
        ['type' => 'difference', 'concept' => 'mixed', 'front' => 'mixed vs a union', 'back' => 'mixed accepts everything (no contract). A union lists the real possibilities, so PHP can still validate.'],
        ['type' => 'difference', 'concept' => 'polymorphism', 'front' => 'interface vs trait (short version)', 'back' => 'An interface declares what a class must do (signatures only). A trait supplies reusable implementation.'],
        ['type' => 'difference', 'concept' => 'polymorphism', 'front' => 'single vs multiple inheritance', 'back' => 'PHP: one parent class (no diamond ambiguity). Behaviour sharing beyond that comes from interfaces and traits.'],
        ['type' => 'difference', 'concept' => 'type_declarations', 'front' => 'return type vs return value', 'back' => 'The declaration is the promise in the signature; the returned value is what PHP checks against it at runtime.'],
        ['type' => 'difference', 'concept' => 'variables', 'front' => 'var vs typed declaration', 'back' => 'Untyped variables take any value; declarations on properties/parameters/returns pin the shape and get enforced.'],

        // --- Snippet cards ---
        ['type' => 'snippet', 'concept' => 'object_identity', 'front' => "```php\n\$a = new stdClass;\n\$b = new stdClass;\nvar_dump(\$a == \$b, \$a === \$b);\n```\nWhat is printed?", 'back' => "bool(true)\nbool(false) - equal shape, different instances."],
        ['type' => 'snippet', 'concept' => 'constructor', 'front' => "```php\nclass Lamp\n{\n    public function __construct() { echo 'on'; }\n}\nnew Lamp;\n```\nWhat is printed?", 'back' => 'on - the constructor runs at instantiation.'],
        ['type' => 'snippet', 'concept' => 'typed_properties', 'front' => "```php\nclass C\n{\n    public int \$n;\n}\n(new C)->n = '5';\n```\nWhat happens?", 'back' => 'TypeError: a string cannot be assigned to an int property (no coercion for typed properties).'],
        ['type' => 'snippet', 'concept' => 'nullable_types', 'front' => "```php\nfunction f(): ?string { return null; }\necho f();\n```\nWhat happens?", 'back' => 'Echoing null prints an empty string (and a deprecation notice on PHP 8.5+ for null to string).'],
        ['type' => 'snippet', 'concept' => 'type_coercion', 'front' => "```php\nfunction twice(int \$n): int { return \$n * 2; }\ntwice('21');\n```\nCoercive mode result?", 'back' => "42 - '21' coerces to int 21. With strict_types it would throw TypeError."],
        ['type' => 'snippet', 'concept' => 'visibility', 'front' => "```php\nclass A\n{\n    private int \$x = 1;\n}\n(new A)->x;\n```\nWhat happens?", 'back' => 'Error: cannot access private property from outside the class.'],
        ['type' => 'snippet', 'concept' => 'inheritance', 'front' => "```php\nclass Animal\n{\n    public function speak(): string { return '...'; }\n}\nclass Dog extends Animal\n{\n    public function speak(): string { return 'Woof'; }\n}\necho (new Dog)->speak();\n```\nOutput?", 'back' => 'Woof - the child override runs.'],
        ['type' => 'snippet', 'concept' => 'typed_properties', 'front' => "```php\nclass C\n{\n    public int \$n;\n}\necho (new C)->n;\n```\nWhat happens?", 'back' => 'Error: uninitialized typed property C::$n (it is not null - it was never given a value).'],
        ['type' => 'snippet', 'concept' => 'named_arguments', 'front' => "```php\nfunction f(int \$a, int \$b = 2): int { return \$a - \$b; }\necho f(b: 10, a: 3);\n```\nOutput?", 'back' => '-7 - named arguments bind by name, not position.'],
        ['type' => 'snippet', 'concept' => 'return_types', 'front' => "```php\nfunction tag(): string {}\ntag();\n```\nWhat happens?", 'back' => 'TypeError: the implicit null return violates the string return type.'],
        ['type' => 'snippet', 'concept' => 'object_identity', 'front' => "```php\n\$a = new stdClass;\n\$b = \$a;\nvar_dump(\$a === \$b);\n```\nOutput?", 'back' => 'bool(true) - $b points at the same instance.'],
        ['type' => 'snippet', 'concept' => 'constructor_promotion', 'front' => "```php\nclass P\n{\n    public function __construct(private string \$name) {}\n}\n(new P('Ada'))->name;\n```\nWhat happens?", 'back' => 'Error: the promoted property is private, so it is invisible from outside the class.'],
    ];

    public function run(): void
    {
        $ord = 0;

        foreach ($this->definitionCards() as $card) {
            $ord++;
            Flashcard::query()->updateOrCreate(
                [
                    'concept_id' => $card['concept_id'],
                    'card_type' => CardType::Definition,
                ],
                [
                    'lesson_id' => $card['lesson_id'],
                    'front' => $card['front'],
                    'back' => $card['back'],
                    'ord' => $ord,
                    'source' => ProvenanceSource::Ai,
                    'status' => ContentStatus::Published,
                    'page_printed_from' => $card['page_printed_from'],
                    'page_printed_to' => $card['page_printed_to'],
                    'page_pdf_from' => $card['page_pdf_from'],
                    'page_pdf_to' => $card['page_pdf_to'],
                    'ai_model' => 'gpt-4o',
                    'ai_generated_at' => now(),
                    'ai_prompt_version' => 'phase4-seed',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'is_outdated' => false,
                ],
            );
        }

        foreach (self::CARDS as $card) {
            $ord++;
            Flashcard::query()->updateOrCreate(
                ['front' => $card['front']],
                [
                    'concept_id' => $this->conceptId($card['concept']),
                    'lesson_id' => null,
                    'card_type' => CardType::from($card['type']),
                    'back' => $card['back'],
                    'ord' => $ord,
                    'source' => ProvenanceSource::Ai,
                    'status' => ContentStatus::Published,
                    'page_printed_from' => null,
                    'page_printed_to' => null,
                    'page_pdf_from' => null,
                    'page_pdf_to' => null,
                    'ai_model' => 'gpt-4o',
                    'ai_generated_at' => now(),
                    'ai_prompt_version' => 'phase4-seed',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'is_outdated' => false,
                ],
            );
        }
    }

    /**
     * One definition card per concept linked to a Chapter 3 lesson, carrying
     * the first linking lesson's citation.
     *
     * @return list<array{concept_id: int, lesson_id: int|null, front: string, back: string, page_printed_from: int|null, page_printed_to: int|null, page_pdf_from: int|null, page_pdf_to: int|null}>
     */
    private function definitionCards(): array
    {
        $rows = Concept::query()
            ->whereHas('lessons', fn ($query) => $query->where('slug', 'like', 'ch3-%'))
            ->with(['lessons' => fn ($query) => $query->where('slug', 'like', 'ch3-%')->orderBy('ord')])
            ->orderBy('name')
            ->get();

        if ($rows->isEmpty()) {
            Log::warning('FlashcardSeeder: no Chapter 3 concepts found. Run ConceptGraphSeeder first.');

            return [];
        }

        $cards = [];

        foreach ($rows as $concept) {
            $lesson = $concept->lessons->first();

            $cards[] = [
                'concept_id' => $concept->id,
                'lesson_id' => $lesson?->id,
                'front' => $concept->name,
                'back' => $concept->definition,
                'page_printed_from' => $lesson?->page_printed_from,
                'page_printed_to' => $lesson?->page_printed_to,
                'page_pdf_from' => $lesson?->page_pdf_from,
                'page_pdf_to' => $lesson?->page_pdf_to,
            ];
        }

        return $cards;
    }

    private function conceptId(?string $slug): ?int
    {
        if ($slug === null) {
            return null;
        }

        $id = Concept::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
