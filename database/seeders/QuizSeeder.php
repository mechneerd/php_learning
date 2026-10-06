<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Lesson;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Lesson quizzes for the Stage 0 + Chapter 3 seed scope (docs/15 phase 4).
 *
 * Three questions per lesson as the MVP floor (docs/04 section 6 asks for
 * 4-6; the Phase 7 content pipeline fills the gap). Questions are published
 * with the provenance block of their lesson; options are rebuilt
 * deterministically on every run.
 *
 * Option grading: is_correct marks the right choice; completion questions
 * grade by exact (case-insensitive) text of the correct option; short answers
 * would do the same, but this seed uses option-backed types only.
 */
final class QuizSeeder extends Seeder
{
    /**
     * lesson slug => 3 questions.
     *
     * concept is the Concept.name as linked to that lesson (resolved
     * through lesson_concepts, null when absent).
     *
     * @var array<string, list<array{type: string, difficulty: string, concept: string|null, stem: string, explanation: string, options: list<array{0: string, 1: bool}>}>>
     */
    private const QUIZ = [
        'ch3-classes-and-objects' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Classes & Objects',
                'stem' => 'Which statement best describes a class?',
                'explanation' => 'A class is a declaration - the template - while objects are the things built from it.',
                'options' => [
                    ['A value stored in a variable', false],
                    ['A blueprint that describes properties and methods', true],
                    ['A function that runs on page load', false],
                    ['A database table', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Classes & Objects',
                'stem' => 'True or false: every object created from the same class shares one set of property values.',
                'explanation' => 'Each object has its own state; only static members are shared across instances.',
                'options' => [
                    ['True', false],
                    ['False - each object holds its own state', true],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Classes & Objects',
                'stem' => "What is printed?\n\n```php\nclass Greeter\n{\n    public function greeting(): string { return 'Hello'; }\n}\n\n\$g = new Greeter;\necho \$g->greeting();\n```",
                'explanation' => 'greeting() returns Hello, which echo prints verbatim.',
                'options' => [
                    ['Hello', true],
                    ['greeting', false],
                    ['Greeter', false],
                    ['Nothing - fatal error', false],
                ],
            ],
        ],

        'ch3-a-first-class' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Classes & Objects',
                'stem' => 'Where do you write a method inside a class?',
                'explanation' => 'Methods are declared in the class body, between its braces.',
                'options' => [
                    ['In a separate file only', false],
                    ['Inside the class body between { and }', true],
                    ['After the closing ?> tag', false],
                    ['Only in a trait', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'easy',
                'concept' => 'Classes & Objects',
                'stem' => 'Complete the declaration: ____ Product { }',
                'explanation' => 'Class declarations start with the class keyword.',
                'options' => [
                    ['class', true],
                    ['object', false],
                    ['new', false],
                    ['struct', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Classes & Objects',
                'stem' => 'True or false: a class can exist without ever being instantiated.',
                'explanation' => 'Sure - you can define a class and never call new. Nothing forces instantiation.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-a-first-object-or-two' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Object Identity',
                'stem' => 'After $a = new stdClass; $b = new stdClass; what does $a === $b evaluate to?',
                'explanation' => '=== compares identity: two separate new expressions always produce different instances.',
                'options' => [
                    ['true', false],
                    ['false', true],
                    ['null', false],
                    ['A TypeError is thrown', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Object Identity',
                'stem' => 'True or false: two objects created from the same class can still be different from each other.',
                'explanation' => 'Yes - same class, different instances, different identity (and potentially different state).',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Object Identity',
                'stem' => "What is printed?\n\n```php\n\$a = new stdClass;\n\$b = \$a;\necho \$a === \$b ? 'same' : 'different';\n```",
                'explanation' => '$b is assigned the same instance, so identity holds.',
                'options' => [
                    ['same', true],
                    ['different', false],
                    ['true', false],
                    ['Warning, nothing printed', false],
                ],
            ],
        ],

        'ch3-setting-properties-in-a-class' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Properties & Methods',
                'stem' => 'Which operator reads and writes an object’s property?',
                'explanation' => '-> connects an object to its members (properties and methods).',
                'options' => [
                    ['::', false],
                    ['->', true],
                    ['.', false],
                    ['=>', false],
                ],
            ],
            [
                'type' => 'debug',
                'difficulty' => 'medium',
                'concept' => 'Properties & Methods',
                'stem' => "This prints an undefined variable warning:\n\n```php\n\$t = new Ticket;\necho \$tickt->price;\n```\n\nWhat is the bug?",
                'explanation' => '$tickt was never assigned - the object lives in $t.',
                'options' => [
                    ['The variable is misspelled: $tickt should be $t', true],
                    ['Ticket needs a constructor', false],
                    ['price must be a method', false],
                    ['echo cannot print properties', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Properties & Methods',
                'stem' => 'True or false: you must declare a property in the class before assigning it on an instance.',
                'explanation' => 'Dynamic properties are deprecated in PHP 8.2 - declare properties in the class body.',
                'options' => [
                    ['True - declare it in the class (dynamic properties are deprecated)', true],
                    ['False - any property can appear at runtime', false],
                ],
            ],
        ],

        'ch3-working-with-methods' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Properties & Methods',
                'stem' => 'What does a return type declaration on a method guarantee?',
                'explanation' => 'PHP checks the returned value against the declared type and throws TypeError on a mismatch.',
                'options' => [
                    ['Nothing - it is only a comment', false],
                    ['The value returned matches the declared type', true],
                    ['The method must be public', false],
                    ['The method cannot use parameters', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'easy',
                'concept' => 'Properties & Methods',
                'stem' => "What is printed?\n\n```php\nclass Rect\n{\n    public function area(int \$w, int \$h): int { return \$w * \$h; }\n}\n\necho (new Rect)->area(3, 4);\n```",
                'explanation' => '3 * 4 = 12.',
                'options' => [
                    ['12', true],
                    ['7', false],
                    ['34', false],
                    ['TypeError', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'medium',
                'concept' => 'Properties & Methods',
                'stem' => 'Complete: $rect = new Rect; echo $rect->____(3, 4);',
                'explanation' => 'The method is called by name through the object with ->.',
                'options' => [
                    ['area', true],
                    ['Rect', false],
                    ['new', false],
                    ['this', false],
                ],
            ],
        ],

        'ch3-creating-a-constructor-method' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Constructor',
                'stem' => 'When does a constructor run?',
                'explanation' => 'The constructor runs at instantiation, when new creates the object.',
                'options' => [
                    ['When the class file is included', false],
                    ['When new instantiates the class', true],
                    ['On the first method call only', false],
                    ['At script shutdown', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'medium',
                'concept' => 'Constructor',
                'stem' => 'Complete the method name: public function ______(string $name) { $this->name = $name; }',
                'explanation' => 'The constructor is always named __construct.',
                'options' => [
                    ['__construct', true],
                    ['construct', false],
                    ['init', false],
                    ['__init__', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Constructor',
                'stem' => 'True or false: a constructor is required for every class.',
                'explanation' => 'Classes work fine without one; a constructor is for guaranteed initialisation.',
                'options' => [
                    ['False', true],
                    ['True', false],
                ],
            ],
        ],

        'ch3-constructor-property-promotion' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Constructor Property Promotion',
                'stem' => 'Which line uses constructor property promotion?',
                'explanation' => 'Promotion puts visibility + type on the constructor parameter itself; PHP declares the property.',
                'options' => [
                    ['public function __construct(string $name) { $this->name = $name; }', false],
                    ['public function __construct(private string $name) {}', true],
                    ['public string $name;', false],
                    ['public function name(string $name) { return $name; }', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Constructor Property Promotion',
                'stem' => 'True or false: promoted properties must be private.',
                'explanation' => 'private, protected or public are all allowed - the choice is an encapsulation decision.',
                'options' => [
                    ['False - any visibility works', true],
                    ['True', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'hard',
                'concept' => 'Constructor Property Promotion',
                'stem' => "What is printed?\n\n```php\nclass P\n{\n    public function __construct(private string \$name) {}\n}\n\n\$p = new P('Ada');\necho \$p->name;\n```",
                'explanation' => 'The promoted property is private, so reaching it from outside throws an Error.',
                'options' => [
                    ['Fatal Error: Cannot access private property', true],
                    ['Ada', false],
                    ['null', false],
                    ['P', false],
                ],
            ],
        ],

        'ch3-default-arguments-and-named-arguments' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Named Arguments',
                'stem' => 'What makes a parameter optional?',
                'explanation' => 'A default value in the signature means callers may omit the argument.',
                'options' => [
                    ['A default value', true],
                    ['The public keyword', false],
                    ['A return type', false],
                    ['Nothing - all parameters are optional', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Named Arguments',
                'stem' => "What is printed?\n\n```php\nfunction f(int \$a, int \$b = 2): int { return \$a - \$b; }\necho f(b: 10, a: 3);\n```",
                'explanation' => 'Named arguments match by name regardless of order: a=3, b=10, so 3-10 = -7.',
                'options' => [
                    ['-7', true],
                    ['13', false],
                    ['-12', false],
                    ['ArgumentError', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Named Arguments',
                'stem' => 'True or false: with named arguments you may skip an argument that has a default.',
                'explanation' => 'Yes - named arguments make skipping optional arguments explicit and safe.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-arguments-and-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Type Coercion',
                'stem' => "Without strict_types, what happens when twice('21') is called on function twice(int \$n)?",
                'explanation' => 'PHP coerces numeric strings to int unless strict_types=1 is declared in the file.',
                'options' => [
                    ['TypeError', false],
                    ['The string coerces to int 21 and the result is 42', true],
                    ["The string is concatenated: '2121'", false],
                    ['null is returned', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'medium',
                'concept' => 'Type Coercion',
                'stem' => 'Complete the directive: declare(_______ = 1); - it must be the first statement in the file.',
                'explanation' => 'strict_types is the per-file switch from coercion to strict checking.',
                'options' => [
                    ['strict_types', true],
                    ['typed', false],
                    ['safe_mode', false],
                    ['strong_types', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Type Coercion',
                'stem' => 'True or false: declare(strict_types=1) affects every file in the project.',
                'explanation' => 'It is per-file: it governs calls made from the file that declares it.',
                'options' => [
                    ['False - it is per-file', true],
                    ['True', false],
                ],
            ],
        ],

        'ch3-primitive-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Primitive Types',
                'stem' => 'Which is NOT a PHP primitive (scalar) type?',
                'explanation' => 'int, string, float and bool are primitives; array is compound.',
                'options' => [
                    ['string', false],
                    ['float', false],
                    ['array', true],
                    ['int', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'easy',
                'concept' => 'Primitive Types',
                'stem' => 'Complete: function setAge(____ $age): ____ { return $age; } - both blanks are one primitive type.',
                'explanation' => 'A whole number is int - declared on parameter and return.',
                'options' => [
                    ['int', true],
                    ['string', false],
                    ['number', false],
                    ['whole', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Variables',
                'stem' => 'True or false: PHP infers a variable’s type from the value assigned to it.',
                'explanation' => 'Variables are untyped; the value carries the type.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-some-other-type-checking-functions' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Type-Checking Functions',
                'stem' => 'Which function tells you whether a value is a string?',
                'explanation' => 'is_string() reports the type without converting anything.',
                'options' => [
                    ['is_string()', true],
                    ['typeof()', false],
                    ['checkString()', false],
                    ['isText()', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'easy',
                'concept' => 'Type-Checking Functions',
                'stem' => "What is printed?\n\n```php\necho is_numeric('12.5') ? 'yes' : 'no';\n```",
                'explanation' => 'Decimal strings are numeric, so is_numeric returns true.',
                'options' => [
                    ['yes', true],
                    ['no', false],
                    ['12.5', false],
                    ['TypeError', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Type-Checking Functions',
                'stem' => 'True or false: gettype() returns the class name of an object.',
                'explanation' => 'gettype() says "object"; use get_class() for the class name.',
                'options' => [
                    ['False - gettype() returns "object"', true],
                    ['True', false],
                ],
            ],
        ],

        'ch3-type-declarations-object-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Type Declarations',
                'stem' => 'What does a class name as a parameter type enforce?',
                'explanation' => 'Only instances of that class (or subclasses) are accepted; anything else throws TypeError.',
                'options' => [
                    ['The argument must be an instance of that class', true],
                    ['The argument must be the class name as a string', false],
                    ['The argument is optional', false],
                    ['No check at all', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Type Declarations',
                'stem' => "What happens?\n\n```php\nfunction lock(Appointment \$slot): string { return 'ok'; }\nlock('tomorrow');\n```",
                'explanation' => 'A string is not an Appointment - object types are never coerced.',
                'options' => [
                    ['TypeError is thrown', true],
                    ["'ok' is printed", false],
                    ['null is returned', false],
                    ['A warning is logged', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Type Declarations',
                'stem' => 'True or false: an object type declaration accepts subclasses of the declared class.',
                'explanation' => 'Yes - instances of child classes satisfy the parent type (Liskov substitution).',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-type-declarations-primitive-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Type Declarations',
                'stem' => "With declare(strict_types=1), what happens when you pass '5' to a parameter declared int?",
                'explanation' => 'Strict mode forbids coercion, so the mismatch throws TypeError.',
                'options' => [
                    ['TypeError', true],
                    ['It coerces to 5', false],
                    ['It stays a string', false],
                    ['null', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'easy',
                'concept' => 'Type Declarations',
                'stem' => 'Complete: function repeat(string $text, int $times): ______ { return str_repeat($text, $times); }',
                'explanation' => 'str_repeat returns a string, so the return type is string.',
                'options' => [
                    ['string', true],
                    ['int', false],
                    ['mixed', false],
                    ['void', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Type Declarations',
                'stem' => 'True or false: return type declarations have been available since PHP 7.',
                'explanation' => 'PHP 7.0 introduced scalar and return types together.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-mixed-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'mixed',
                'stem' => 'What does the mixed type accept?',
                'explanation' => 'mixed is the union of everything: int|string|float|bool|array|object|callable|null.',
                'options' => [
                    ['Only scalar values', false],
                    ['Every possible type', true],
                    ['Only arrays and objects', false],
                    ['Only null and scalars', false],
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'mixed',
                'stem' => 'What do you give up when a parameter is declared mixed?',
                'explanation' => 'PHP can no longer validate anything - the contract is gone and validation moves inside the function.',
                'options' => [
                    ['Type enforcement by PHP', true],
                    ['Readability', false],
                    ['Performance only', false],
                    ['Nothing', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'mixed',
                'stem' => 'True or false: mixed can also be used as a return type.',
                'explanation' => 'Yes - mixed works anywhere a type declaration is allowed (properties excepted before 8.0 is irrelevant now).',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-union-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Union Types',
                'stem' => 'Which declaration accepts an int or a string?',
                'explanation' => 'The | operator joins types into a union.',
                'options' => [
                    ['int|string', true],
                    ['int & string', false],
                    ['int or string', false],
                    ['int+string', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Union Types',
                'stem' => "What is printed?\n\n```php\nfunction label(int|string \$v): string\n{\n    return is_int(\$v) ? 'int' : 'string';\n}\necho label('7');\n```",
                'explanation' => 'The union accepts the string but does not convert it - is_int is false.',
                'options' => [
                    ['string', true],
                    ['int', false],
                    ['7', false],
                    ['TypeError', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Union Types',
                'stem' => 'True or false: union types arrived in PHP 8.0.',
                'explanation' => 'PHP 8.0 added unions; 7.1 had only the nullable shorthand.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-nullable-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Nullable Types',
                'stem' => 'Which declaration accepts an int or null?',
                'explanation' => '?int is shorthand for int|null.',
                'options' => [
                    ['?int', true],
                    ['int?', false],
                    ['int-null', false],
                    ['int2', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Nullable Types',
                'stem' => "What is printed?\n\n```php\nfunction findUser(int \$id): ?array\n{\n    if (\$id < 1) { return null; }\n    return ['id' => \$id];\n}\nvar_dump(findUser(0));\n```",
                'explanation' => 'id 0 is less than 1, so the function returns null.',
                'options' => [
                    ['NULL', true],
                    ['array(0) {}', false],
                    ['false', false],
                    ['TypeError', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Nullable Types',
                'stem' => 'True or false: ?array means the function may return an array or null.',
                'explanation' => 'Exactly - the ? admits null alongside the declared type.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-return-type-declarations' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Return Types',
                'stem' => 'array_sum() may return int or float. Which return type is honest?',
                'explanation' => 'Declare the union: both outcomes are possible.',
                'options' => [
                    ['int|float', true],
                    ['mixed', false],
                    ['number', false],
                    ['any', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'hard',
                'concept' => 'Return Types',
                'stem' => "What happens?\n\n```php\nfunction tag(): string\n{\n}\ntag();\n```",
                'explanation' => 'The function implicitly returns null, violating string - PHP throws TypeError.',
                'options' => [
                    ['TypeError is thrown', true],
                    ['Empty string is printed', false],
                    ['null is returned silently', false],
                    ['Warning only', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'easy',
                'concept' => 'Return Types',
                'stem' => 'Complete: function area(int $w, int $h): ______ { return $w * $h; }',
                'explanation' => 'The product of two ints is an int.',
                'options' => [
                    ['int', true],
                    ['void', false],
                    ['number', false],
                    ['mixed', false],
                ],
            ],
        ],

        'ch3-inheritance' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Inheritance',
                'stem' => 'Which keyword creates a subclass?',
                'explanation' => 'class Dog extends Animal makes Dog the subclass.',
                'options' => [
                    ['extends', true],
                    ['implements', false],
                    ['inherits', false],
                    ['uses', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Inheritance',
                'stem' => 'True or false: a subclass inherits private methods of its parent.',
                'explanation' => 'Private members belong to the declaring class only - subclasses cannot call them.',
                'options' => [
                    ['False - private members stay with the parent class', true],
                    ['True', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Inheritance',
                'stem' => "What is printed?\n\n```php\nclass Animal\n{\n    public function speak(): string { return '...'; }\n}\nclass Dog extends Animal\n{\n    public function speak(): string { return 'Woof'; }\n}\necho (new Dog)->speak();\n```",
                'explanation' => 'The child overrides speak(), so the Dog implementation runs.',
                'options' => [
                    ['Woof', true],
                    ['...', false],
                    ['Animal', false],
                    ['Fatal error', false],
                ],
            ],
        ],

        'ch3-the-inheritance-problem' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Polymorphism',
                'stem' => 'Why does PHP allow only one parent class?',
                'explanation' => 'Multiple inheritance creates the diamond problem - ambiguous state and method resolution.',
                'options' => [
                    ['To avoid the diamond problem', true],
                    ['Because PHP is single-threaded', false],
                    ['Because interfaces exist', false],
                    ['It is a database limit', false],
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Polymorphism',
                'stem' => 'Which pair gives PHP both contracts and shared implementation without multiple inheritance?',
                'explanation' => 'Interfaces define contracts; traits mix in implementation.',
                'options' => [
                    ['Interfaces and traits', true],
                    ['abstract and final', false],
                    ['extends and new', false],
                    ['static and self', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'easy',
                'concept' => 'Polymorphism',
                'stem' => 'True or false: polymorphism means the same call can behave differently depending on the object.',
                'explanation' => 'That is the idea - one interface, many implementations.',
                'options' => [
                    ['True', true],
                    ['False', false],
                ],
            ],
        ],

        'ch3-working-with-inheritance' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Inheritance',
                'stem' => 'How does a child method call the parent’s overridden method?',
                'explanation' => 'parent::methodName() reaches the inherited implementation.',
                'options' => [
                    ['parent::speak()', true],
                    ['self::speak()', false],
                    ['$this->parent->speak()', false],
                    ['super.speak()', false],
                ],
            ],
            [
                'type' => 'debug',
                'difficulty' => 'hard',
                'concept' => 'Inheritance',
                'stem' => "Fatal error: Declaration of Dog::speak(): int must be compatible with Animal::speak(): string. What must change?\n\n```php\nclass Animal { public function speak(): string { return '...'; } }\nclass Dog extends Animal { public function speak(): int { return 1; } }\n```",
                'explanation' => 'Overrides must be compatible - Dog::speak() needs return type string (or a subtype).',
                'options' => [
                    ['Give Dog::speak() the return type string', true],
                    ['Delete Animal::speak()', false],
                    ['Make speak() private in Animal', false],
                    ['Add a constructor to Dog', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Inheritance',
                'stem' => 'True or false: an override may narrow parameter types compared to the parent.',
                'explanation' => 'Parameter contravariance allows wider (not narrower) parameters in PHP - narrowing is fatal.',
                'options' => [
                    ['False - parameters may only stay the same or get wider', true],
                    ['True', false],
                ],
            ],
        ],

        'ch3-public-private-and-protected' => [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'Visibility & Encapsulation',
                'stem' => 'Which visibility is accessible from a subclass but nowhere else?',
                'explanation' => 'protected is scoped to the class and its descendants.',
                'options' => [
                    ['protected', true],
                    ['public', false],
                    ['private', false],
                    ['internal', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'medium',
                'concept' => 'Visibility & Encapsulation',
                'stem' => "What happens?\n\n```php\nclass Account\n{\n    private int \$balance = 0;\n}\n\$a = new Account;\necho \$a->balance;\n```",
                'explanation' => 'Reaching a private property from outside the class throws an Error.',
                'options' => [
                    ['Error: Cannot access private property', true],
                    ['0', false],
                    ['null', false],
                    ['Warning, prints nothing', false],
                ],
            ],
            [
                'type' => 'completion',
                'difficulty' => 'easy',
                'concept' => 'Visibility & Encapsulation',
                'stem' => 'Complete: ____ function balance(): int { return $this->balance; } - the getter must be reachable from outside.',
                'explanation' => 'Getters are public so clients can read state through behaviour.',
                'options' => [
                    ['public', true],
                    ['private', false],
                    ['protected', false],
                    ['static', false],
                ],
            ],
        ],

        'ch3-typed-properties' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Typed Properties',
                'stem' => 'Where does the type check for a property assignment happen?',
                'explanation' => 'On the assignment itself - the declaration enforces it, no setter required.',
                'options' => [
                    ['At assignment time, checked by PHP', true],
                    ['Only if you write a setter', false],
                    ['At compile time only', false],
                    ['Never automatically', false],
                ],
            ],
            [
                'type' => 'output',
                'difficulty' => 'hard',
                'concept' => 'Typed Properties',
                'stem' => "What happens?\n\n```php\nclass Config\n{\n    public int \$retries;\n}\n\$c = new Config;\necho \$c->retries;\n```",
                'explanation' => 'No default means uninitialized - reading an uninitialized typed property throws Error.',
                'options' => [
                    ['Error: must not be accessed before initialization', true],
                    ['0', false],
                    ['null', false],
                    ['false', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Typed Properties',
                'stem' => 'True or false: assigning a string to a typed int property throws immediately.',
                'explanation' => 'TypeError is raised on the offending assignment.',
                'options' => [
                    ['True', true],
                    ['False - it coerces silently', false],
                ],
            ],
        ],

        'ch3-summary' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Classes & Objects',
                'stem' => 'Which chapter tool enforces data shape at assignment time?',
                'explanation' => 'Type declarations (on properties, parameters and returns) validate values; visibility only controls access.',
                'options' => [
                    ['Type declarations', true],
                    ['Inheritance', false],
                    ['Visibility', false],
                    ['Named arguments', false],
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'Visibility & Encapsulation',
                'stem' => 'What does encapsulation mean in one sentence?',
                'explanation' => 'State stays behind behaviour: clients use the public door, not the fields.',
                'options' => [
                    ['Keeping state behind behaviour and controlled access', true],
                    ['Making every property public', false],
                    ['Copying parent code', false],
                    ['Declaring types on returns', false],
                ],
            ],
            [
                'type' => 'true_false',
                'difficulty' => 'medium',
                'concept' => 'Inheritance',
                'stem' => 'True or false: PHP allows a class to extend several parent classes.',
                'explanation' => 'Single inheritance only - traits and interfaces cover the rest.',
                'options' => [
                    ['False', true],
                    ['True', false],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::QUIZ as $slug => $questions) {
            $lesson = Lesson::query()->where('slug', $slug)->first();

            if ($lesson === null) {
                Log::warning('QuizSeeder: lesson '.$slug.' not found - skipped.');

                continue;
            }

            foreach ($questions as $index => $data) {
                $question = QuizQuestion::query()->updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'ord' => $index + 1,
                    ],
                    [
                        'concept_id' => $this->conceptId($lesson, $data['concept']),
                        'type' => $data['type'],
                        'stem' => $data['stem'],
                        'explanation' => $data['explanation'],
                        'difficulty' => $data['difficulty'],
                        'source' => ProvenanceSource::Ai,
                        'status' => ContentStatus::Published,
                        'page_printed_from' => $lesson->page_printed_from,
                        'page_printed_to' => $lesson->page_printed_to,
                        'page_pdf_from' => $lesson->page_pdf_from,
                        'page_pdf_to' => $lesson->page_pdf_to,
                        'ai_model' => 'gpt-4o',
                        'ai_generated_at' => now(),
                        'ai_prompt_version' => 'phase4-seed',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'is_outdated' => false,
                    ],
                );

                $question->options()->delete();

                foreach ($data['options'] as $optionIndex => [$text, $correct]) {
                    QuizOption::query()->create([
                        'question_id' => $question->id,
                        'text' => $text,
                        'is_correct' => $correct,
                        'feedback' => null,
                        'ord' => $optionIndex + 1,
                    ]);
                }
            }
        }
    }

    private function conceptId(Lesson $lesson, ?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $id = $lesson->concepts()->where('name', $name)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
