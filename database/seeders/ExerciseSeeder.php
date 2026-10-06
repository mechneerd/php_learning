<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Practice exercises for the Stage 0 + Chapter 3 seed scope (docs/15 phase 4).
 *
 * Every exercise carries the provenance block of the lesson it belongs to
 * (pages come from the lesson's own citation) and is published so the
 * practice runner picks it up immediately. Hints and tests are rebuilt
 * deterministically on every run.
 *
 * Grading is static-only until Phase 8 (docs/12): write/complete/fix/problem
 * exercises are checked with assert_contains / assert_regex / static_check
 * rules; predict_output / find_error / mcq compare stored answers; explain
 * uses a keyword rubric.
 */
final class ExerciseSeeder extends Seeder
{
    /**
     * lesson slug => list of exercises.
     *
     * Each exercise: type, difficulty, concept slug (nullable), prompt,
     * starter, solution, explanation, tests ([type, payload, weight]),
     * hints (3 levels).
     *
     * @var array<string, list<array{type: string, difficulty: string, concept: string|null, prompt: string, starter: string, solution: string, explanation: string, tests: list<array{0: string, 1: array<string, mixed>, 2: int}>, hints: list<string>}>>
     */
    private static array $exercises = [
        'ch3-classes-and-objects' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'class',
                'prompt' => "Declare a class called Greeter with one method greeting() that returns the string 'Hello'. Static checks only until Phase 8 - make sure the class and method declarations are exactly right.",
                'starter' => "<?php\n\n// declare class Greeter here\n",
                'solution' => "<?php\n\nclass Greeter\n{\n    public function greeting(): string\n    {\n        return 'Hello';\n    }\n}\n",
                'explanation' => 'A class declares the blueprint; greeting() is one of its methods. Return types make the contract explicit.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Greeter'], 2],
                    ['assert_regex', ['pattern' => 'function\s+greeting\s*\('], 2],
                ],
                'hints' => [
                    'A class declaration starts with the class keyword followed by the class name.',
                    'Methods are declared inside the class body with the function keyword.',
                    'Return the string with a return statement and declare : string on the method.',
                ],
            ],
            [
                'type' => 'explain',
                'difficulty' => 'easy',
                'concept' => 'class',
                'prompt' => 'In your own words, what is the difference between a class and an object? Mention the keyword used to create one. Keywords graded: class, object, new, instance.',
                'starter' => '',
                'solution' => 'A class is the blueprint that describes properties and methods. An object is a concrete instance of that class, created with the new keyword.',
                'explanation' => 'The class is the definition; every object is an instance built from that definition at runtime.',
                'tests' => [],
                'hints' => [
                    'Think: definition versus thing made from the definition.',
                    'You create one with the new keyword.',
                    'Each thing created is called an instance.',
                ],
            ],
        ],

        'ch3-a-first-class' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'class',
                'prompt' => 'Write a class Product with a public property $name and a method price() that returns the integer 10.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Product\n{\n    public \$name;\n\n    public function price(): int\n    {\n        return 10;\n    }\n}\n",
                'explanation' => 'Properties live directly in the class body; methods operate on them.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Product'], 2],
                    ['assert_regex', ['pattern' => 'public\s+\$name'], 1],
                    ['assert_regex', ['pattern' => 'function\s+price\s*\('], 1],
                ],
                'hints' => [
                    'Declare the class first, then its members inside the braces.',
                    'A property is a variable declared directly in the class body.',
                    'price() should return the int literal 10 with a : int return type.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'class',
                'prompt' => "Which keyword creates an instance of a class? Answer with the single keyword.\n\nA) class\nB) new\nC) instance\nD) create",
                'starter' => '',
                'solution' => 'new',
                'explanation' => 'new is the only keyword that turns a class definition into an object.',
                'tests' => [],
                'hints' => [
                    'It is an expression, not a declaration.',
                    'You saw it in `$greeting = new Greeter();`.',
                    'The answer is B.',
                ],
            ],
        ],

        'ch3-a-first-object-or-two' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'object_identity',
                'prompt' => 'Create a class Counter (empty body is fine), then create two instances of it and assign them to variables named $first and $second.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Counter\n{\n}\n\n\$first = new Counter;\n\$second = new Counter;\n",
                'explanation' => 'Each new expression produces a distinct object, even for the same class.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Counter'], 1],
                    ['assert_regex', ['pattern' => '\$first\s*=\s*new\s+Counter'], 2],
                    ['assert_regex', ['pattern' => '\$second\s*=\s*new\s+Counter'], 2],
                ],
                'hints' => [
                    'Declare the class before you use it.',
                    'Each instance needs its own assignment.',
                    'Use `new Counter;` twice - once for $first, once for $second.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'medium',
                'concept' => 'object_identity',
                'prompt' => "What is printed?\n\n```php\n\$a = new stdClass;\n\$b = new stdClass;\necho \$a === \$b ? 'same' : 'different';\n```",
                'starter' => '',
                'solution' => 'different',
                'explanation' => '=== compares identity: two separate objects are never the same instance.',
                'tests' => [],
                'hints' => [
                    '=== checks identity, not just shape.',
                    'Each new expression allocates a fresh object.',
                    'The two variables point at different objects, so the ternary picks the else branch.',
                ],
            ],
        ],

        'ch3-setting-properties-in-a-class' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'properties',
                'prompt' => 'Write a class Ticket with a public property $price, then create an instance stored in $t and set its price to 10.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Ticket\n{\n    public \$price;\n}\n\n\$t = new Ticket;\n\$t->price = 10;\n",
                'explanation' => 'Object state is read and written with the -> operator.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Ticket'], 1],
                    ['assert_regex', ['pattern' => 'public\s+\$price'], 2],
                    ['assert_regex', ['pattern' => '\$t->price\s*=\s*10'], 2],
                ],
                'hints' => [
                    'Declare the property inside the class body.',
                    'Instantiate the class with new.',
                    'Assign through the arrow operator: $t->price = 10;',
                ],
            ],
            [
                'type' => 'find_error',
                'difficulty' => 'easy',
                'concept' => 'properties',
                'prompt' => "This code fails with an undefined variable warning:\n\n```php\n\$t = new Ticket;\necho \$tickt->price;\n```\n\nType the misspelled variable name exactly (including the \$).\n\nA) \$t\nB) \$tickt\nC) price\nD) Ticket",
                'starter' => '',
                'solution' => '$tickt',
                'explanation' => 'The second line uses $tickt, which was never assigned - the object lives in $t.',
                'tests' => [],
                'hints' => [
                    'Look at every variable name, character by character.',
                    'One of them was assigned; the other was not.',
                    'The answer is B.',
                ],
            ],
        ],

        'ch3-working-with-methods' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'properties',
                'prompt' => 'Write a class Rect with a method area(int $w, int $h): int that returns the product of its two arguments.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Rect\n{\n    public function area(int \$w, int \$h): int\n    {\n        return \$w * \$h;\n    }\n}\n",
                'explanation' => 'Methods receive arguments like functions and can declare types on each parameter and the return.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Rect'], 1],
                    ['assert_regex', ['pattern' => 'function\s+area\s*\(\s*int\s+\$w\s*,\s*int\s+\$h\s*\)\s*:\s*int'], 3],
                    ['assert_contains', ['needle' => '$w * $h'], 1],
                ],
                'hints' => [
                    'The method signature carries the parameter types.',
                    'Declare both parameters as int and the return as int.',
                    'Multiply the two parameters in the return statement.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'easy',
                'concept' => 'properties',
                'prompt' => "What is printed?\n\n```php\nclass Adder\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n}\n\necho (new Adder)->add(2, 3);\n```",
                'starter' => '',
                'solution' => '5',
                'explanation' => '2 + 3 = 5; echo prints the returned integer without decimals.',
                'tests' => [],
                'hints' => [
                    'Follow the values through the method call.',
                    'add() returns the sum of its two arguments.',
                    'The sum of 2 and 3 is 5.',
                ],
            ],
        ],

        'ch3-creating-a-constructor-method' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'constructor',
                'prompt' => 'Write a class User with a private property $name, a constructor __construct(string $name) that stores the argument into $this->name, and a method name(): string that returns it.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass User\n{\n    private string \$name;\n\n    public function __construct(string \$name)\n    {\n        \$this->name = \$name;\n    }\n\n    public function name(): string\n    {\n        return \$this->name;\n    }\n}\n",
                'explanation' => 'The constructor runs at instantiation and gives every object valid state from the start.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class User'], 1],
                    ['assert_regex', ['pattern' => 'function\s+__construct\s*\(\s*string\s+\$name\s*\)'], 2],
                    ['assert_contains', ['needle' => '$this->name = $name'], 2],
                    ['assert_regex', ['pattern' => 'function\s+name\s*\(\s*\)\s*:\s*string'], 1],
                ],
                'hints' => [
                    'The constructor is named __construct.',
                    'Assign the parameter to the property with $this->name = $name;',
                    'name() just returns $this->name.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'medium',
                'concept' => 'constructor',
                'prompt' => "What is printed?\n\n```php\nclass Lamp\n{\n    public function __construct()\n    {\n        echo 'on';\n    }\n}\n\nnew Lamp;\n```",
                'starter' => '',
                'solution' => 'on',
                'explanation' => 'The constructor body runs the moment the object is created with new.',
                'tests' => [],
                'hints' => [
                    'Where does the echo live?',
                    'The constructor runs during instantiation.',
                    'Nothing else prints - the answer is on.',
                ],
            ],
        ],

        'ch3-constructor-property-promotion' => [
            [
                'type' => 'complete',
                'difficulty' => 'medium',
                'concept' => 'constructor_promotion',
                'prompt' => "Fill in the blanks so the constructor promotes a private string property called \$name:\n\n```php\npublic function __construct(__________ string \$name) {}\n```\n\nAdd whatever is missing in front of `string \$name` (the blank is one visibility modifier).",
                'starter' => "<?php\n\nclass Person\n{\n    public function __construct(__________ string \$name) {}\n}\n",
                'solution' => "<?php\n\nclass Person\n{\n    public function __construct(private string \$name) {}\n}\n",
                'explanation' => 'Promotion puts the visibility modifier on the constructor parameter and PHP declares the property for you.',
                'tests' => [
                    ['assert_regex', ['pattern' => '__construct\s*\(\s*private\s+string\s+\$name\s*\)'], 3],
                ],
                'hints' => [
                    'A promoted property needs a visibility keyword on the parameter.',
                    'Choose private, protected or public - this one should not be public.',
                    'The blank is `private`.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'constructor_promotion',
                'prompt' => "Which constructor is property promotion?\n\nA) public function __construct(string \$name) { \$this->name = \$name; }\nB) public function __construct(private string \$name) {}\nC) public string \$name;\nD) function name(string \$name) { return \$name; }",
                'starter' => '',
                'solution' => 'B',
                'explanation' => 'Promotion is a visibility + type on the constructor parameter itself, with no manual assignment.',
                'tests' => [],
                'hints' => [
                    'Promotion removes the assignment line.',
                    'The modifier sits on the parameter list, not the property body.',
                    'The answer is B.',
                ],
            ],
        ],

        'ch3-default-arguments-and-named-arguments' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'named_arguments',
                'prompt' => "Write a function greet(string \$name, string \$greeting = 'Hi') that returns the greeting, a space, and the name (e.g. 'Hi Ana').",
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction greet(string \$name, string \$greeting = 'Hi'): string\n{\n    return \$greeting.' '.\$name;\n}\n",
                'explanation' => 'A parameter with a default becomes optional; callers may omit it.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'function\s+greet\s*\(\s*string\s+\$name\s*,\s*string\s+\$greeting\s*='], 3],
                    ['assert_contains', ['needle' => "greeting.' '"], 1],
                ],
                'hints' => [
                    'The second parameter carries the default value after the = sign.',
                    "Declare it as `string \$greeting = 'Hi'`.",
                    'Concatenate greeting, a space, then name.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'medium',
                'concept' => 'named_arguments',
                'prompt' => "What is printed?\n\n```php\nfunction f(int \$a, int \$b = 2): int\n{\n    return \$a - \$b;\n}\n\necho f(b: 10, a: 3);\n```",
                'starter' => '',
                'solution' => '-7',
                'explanation' => 'Named arguments can appear in any order: a=3, b=10, so 3 - 10 = -7.',
                'tests' => [],
                'hints' => [
                    'Named arguments match by name, not position.',
                    'a is 3 and b is 10 regardless of call order.',
                    '3 minus 10 is -7.',
                ],
            ],
        ],

        'ch3-arguments-and-types' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'type_coercion',
                'prompt' => "Without strict_types, what does this return?\n\n```php\nfunction twice(int \$n): int { return \$n * 2; }\ntwice('21');\n```\n\nA) TypeError\nB) 42\nC) '2121'\nD) null",
                'starter' => '',
                'solution' => 'B',
                'explanation' => 'PHP coerces the numeric string 21 to int 21 unless declare(strict_types=1) is in effect.',
                'tests' => [],
                'hints' => [
                    'Coercion converts compatible values silently by default.',
                    "The string '21' looks like an integer.",
                    'The answer is B.',
                ],
            ],
            [
                'type' => 'explain',
                'difficulty' => 'medium',
                'concept' => 'type_coercion',
                'prompt' => 'Explain how you switch a file from coercive typing to strict typing. Keywords graded: strict_types, declare, coercion.',
                'starter' => '',
                'solution' => 'Add declare(strict_types=1); as the very first statement in the file. It disables coercion for calls made from that file.',
                'explanation' => 'strict_types is a per-file directive and must be the first statement - it stops coercive conversions for calls originating there.',
                'tests' => [],
                'hints' => [
                    'It is a declare statement.',
                    'Position matters: it must be the first line.',
                    'The value is 1.',
                ],
            ],
        ],

        'ch3-primitive-types' => [
            [
                'type' => 'complete',
                'difficulty' => 'easy',
                'concept' => 'primitive_types',
                'prompt' => "Add the right primitive type declarations:\n\n```php\nfunction setAge(____ \$age): ____ {\n    return \$age;\n}\n```\n\nBoth blanks are the same primitive type for a whole number.",
                'starter' => "<?php\n\nfunction setAge(____ \$age): ____\n{\n    return \$age;\n}\n",
                'solution' => "<?php\n\nfunction setAge(int \$age): int\n{\n    return \$age;\n}\n",
                'explanation' => 'Whole numbers are int - declare it on the parameter and the return.',
                'tests' => [
                    ['static_check', ['required' => ['setAge\s*\(\s*int\s+\$age\s*\)\s*:\s*int']], 3],
                ],
                'hints' => [
                    'A whole number has a four-letter primitive type.',
                    'The parameter and the return use the same type.',
                    'Both blanks are int.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'primitive_types',
                'prompt' => "Which is NOT one of PHP's primitive types?\n\nA) int\nB) string\nC) array\nD) float",
                'starter' => '',
                'solution' => 'C',
                'explanation' => 'int, string, float and bool are primitives; array is a compound type.',
                'tests' => [],
                'hints' => [
                    'Primitives are single scalar values.',
                    'One option holds many values in a structure.',
                    'The answer is C.',
                ],
            ],
        ],

        'ch3-some-other-type-checking-functions' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'type_checking',
                'prompt' => 'Write a function isText(mixed $value): bool that returns true only when $value is a string, using the right type-checking function.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction isText(mixed \$value): bool\n{\n    return is_string(\$value);\n}\n",
                'explanation' => 'is_string() is the dedicated check; it never converts, it only reports.',
                'tests' => [
                    ['assert_contains', ['needle' => 'is_string($value)'], 3],
                    ['assert_regex', ['pattern' => 'function\s+isText\s*\(\s*mixed\s+\$value\s*\)\s*:\s*bool'], 1],
                ],
                'hints' => [
                    'There is a family of is_*() checks.',
                    'The one for strings is is_string().',
                    'Return its result directly.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'type_checking',
                'prompt' => "What does is_numeric('12.5') return?\n\nA) true\nB) false\nC) 12.5\nD) TypeError",
                'starter' => '',
                'solution' => 'A',
                'explanation' => 'is_numeric reports whether the value looks like a number; decimal strings qualify.',
                'tests' => [],
                'hints' => [
                    'The question asks what the check returns, not the number itself.',
                    'Decimal strings count as numeric.',
                    'The answer is A.',
                ],
            ],
        ],

        'ch3-type-declarations-object-types' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'type_declarations',
                'prompt' => "Write a function describe(Appointment \$slot): string whose body returns the string 'booked'. The parameter must be typed with the class Appointment.",
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction describe(Appointment \$slot): string\n{\n    return 'booked';\n}\n",
                'explanation' => 'A class name as a parameter type means only instances of that class (or subclasses) are accepted.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'function\s+describe\s*\(\s*Appointment\s+\$slot\s*\)\s*:\s*string'], 3],
                    ['assert_contains', ['needle' => "'booked'"], 1],
                ],
                'hints' => [
                    'The type goes between ( and the variable name.',
                    'Use the class name exactly as given, capital A.',
                    'Return the quoted string.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'medium',
                'concept' => 'type_declarations',
                'prompt' => "What happens?\n\n```php\nfunction lock(Appointment \$slot): string { return 'ok'; }\nlock('tomorrow');\n```\n\nAnswer with the class name of the thrown error.",
                'starter' => '',
                'solution' => 'TypeError',
                'explanation' => 'A string where an Appointment is declared throws TypeError - object types are not coerced.',
                'tests' => [],
                'hints' => [
                    'Class types cannot be faked from a scalar.',
                    'PHP throws when a type declaration is violated at call time.',
                    'The error class is TypeError.',
                ],
            ],
        ],

        'ch3-type-declarations-primitive-types' => [
            [
                'type' => 'complete',
                'difficulty' => 'medium',
                'concept' => 'type_declarations',
                'prompt' => "Fill in the blanks with primitive types:\n\n```php\nfunction repeat(____ \$text, ____ \$times): ____ {\n    return str_repeat(\$text, \$times);\n}\n```\n\nFirst blank: text. Second and third: a whole number count, with the return matching str_repeat's result.",
                'starter' => "<?php\n\nfunction repeat(____ \$text, ____ \$times): ____\n{\n    return str_repeat(\$text, \$times);\n}\n",
                'solution' => "<?php\n\nfunction repeat(string \$text, int \$times): string\n{\n    return str_repeat(\$text, \$times);\n}\n",
                'explanation' => 'string, int, string: types describe data shape on the way in and out.',
                'tests' => [
                    ['static_check', ['required' => ['repeat\s*\(\s*string\s+\$text\s*,\s*int\s+\$times\s*\)\s*:\s*string']], 3],
                ],
                'hints' => [
                    'The first parameter is text.',
                    'The count is a whole number.',
                    'str_repeat returns a string, so the return type is string.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'type_declarations',
                'prompt' => "With declare(strict_types=1), what happens when you pass '5' (string) to a parameter declared int?\n\nA) It becomes 5\nB) TypeError\nC) null\nD) It stays '5'",
                'starter' => '',
                'solution' => 'B',
                'explanation' => 'strict_types forbids coercion, so the mismatch throws TypeError immediately.',
                'tests' => [],
                'hints' => [
                    'Strict mode removes lenient conversions.',
                    'A violation of a declared type is an error, not a warning.',
                    'The answer is B.',
                ],
            ],
        ],

        'ch3-mixed-types' => [
            [
                'type' => 'write',
                'difficulty' => 'easy',
                'concept' => 'mixed',
                'prompt' => 'Write a function logValue(mixed $value): void that calls var_dump($value).',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction logValue(mixed \$value): void\n{\n    var_dump(\$value);\n}\n",
                'explanation' => 'mixed accepts anything - the widest declaration PHP has.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'function\s+logValue\s*\(\s*mixed\s+\$value\s*\)\s*:\s*void'], 3],
                    ['assert_contains', ['needle' => 'var_dump($value)'], 1],
                ],
                'hints' => [
                    'mixed is the wildcard type.',
                    'Declare both the parameter and the return (nothing is returned).',
                    'The body is a single var_dump call.',
                ],
            ],
            [
                'type' => 'explain',
                'difficulty' => 'medium',
                'concept' => 'mixed',
                'prompt' => 'When is mixed the right choice for a parameter type, and what do you give up by using it? Keywords graded: mixed, union, validation, contract.',
                'starter' => '',
                'solution' => 'mixed is right when the caller may legitimately pass any type, for example a generic dump() helper. You give up the contract: PHP cannot validate anything, so the function itself must handle or validate each case - a union type is better when you know the realistic set of types.',
                'explanation' => 'mixed trades enforcement for flexibility; when the realistic set is small, a union type restores the contract.',
                'tests' => [],
                'hints' => [
                    'Think about what PHP can no longer check for you.',
                    'Compare it with listing the actual types with |.',
                    'Mention that validation moves inside the function.',
                ],
            ],
        ],

        'ch3-union-types' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'union_types',
                'prompt' => 'Write a function id(int|string $value): int|string that returns $value unchanged.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction id(int|string \$value): int|string\n{\n    return \$value;\n}\n",
                'explanation' => 'The | operator lists every accepted type on parameter and return alike.',
                'tests' => [
                    ['assert_contains', ['needle' => 'int|string $value'], 3],
                    ['assert_regex', ['pattern' => '\)\s*:\s*int\|string'], 2],
                ],
                'hints' => [
                    'Union types join types with the pipe character.',
                    'Both parameter and return list int|string.',
                    'The body is just return $value;',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'medium',
                'concept' => 'union_types',
                'prompt' => "What is printed?\n\n```php\nfunction label(int|string \$v): string\n{\n    return is_int(\$v) ? 'int' : 'string';\n}\n\necho label('7');\n```",
                'starter' => '',
                'solution' => 'string',
                'explanation' => "'7' stays a string through the union, so is_int() is false and the ternary picks 'string'.",
                'tests' => [],
                'hints' => [
                    'Does the union convert the argument? No - unions only widen acceptance.',
                    "The argument '7' is still a string inside the function.",
                    'is_int() returns false, so the answer is string.',
                ],
            ],
        ],

        'ch3-nullable-types' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'nullable_types',
                'prompt' => 'Write a function findUser(int $id): ?array that returns null when $id is less than 1, and an array containing the id otherwise.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nfunction findUser(int \$id): ?array\n{\n    if (\$id < 1) {\n        return null;\n    }\n\n    return ['id' => \$id];\n}\n",
                'explanation' => '?array means array or null - the return type documents the miss case.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'function\s+findUser\s*\(\s*int\s+\$id\s*\)\s*:\s*\?array'], 3],
                    ['assert_contains', ['needle' => 'return null'], 2],
                ],
                'hints' => [
                    'The ? before the type admits null.',
                    'Guard first: when the id is invalid, return null.',
                    'Otherwise return an array with the id.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'nullable_types',
                'prompt' => "Which declaration accepts both an int and null?\n\nA) int\nB) ?int\nC) mixed\nD) int|null and only ?int\n\nType the single letter of the best answer for declaring a nullable int.",
                'starter' => '',
                'solution' => 'B',
                'explanation' => '?int is int|null in shorthand; mixed would also accept everything else.',
                'tests' => [],
                'hints' => [
                    'The question asks for the declaration of a nullable int specifically.',
                    'A prefix character makes a type nullable.',
                    'The answer is B.',
                ],
            ],
        ],

        'ch3-return-type-declarations' => [
            [
                'type' => 'complete',
                'difficulty' => 'medium',
                'concept' => 'return_types',
                'prompt' => "Complete the return type:\n\n```php\nfunction sum(array \$nums): ______ {\n    return array_sum(\$nums);\n}\n```\n\narray_sum returns a whole number or a decimal, so declare a union of the two primitive types.",
                'starter' => "<?php\n\nfunction sum(array \$nums): ______\n{\n    return array_sum(\$nums);\n}\n",
                'solution' => "<?php\n\nfunction sum(array \$nums): int|float\n{\n    return array_sum(\$nums);\n}\n",
                'explanation' => 'array_sum can return int or float, so the honest return type is the union int|float.',
                'tests' => [
                    ['static_check', ['required' => ['sum\s*\(\s*array\s+\$nums\s*\)\s*:\s*int\|float']], 3],
                ],
                'hints' => [
                    'You need both number primitives joined together.',
                    'Use the | operator between them.',
                    'The union is int|float.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'hard',
                'concept' => 'return_types',
                'prompt' => "What happens when this runs?\n\n```php\nfunction tag(): string\n{\n}\n\ntag();\n```\n\nAnswer with the class name of the thrown error.",
                'starter' => '',
                'solution' => 'TypeError',
                'explanation' => 'The function implicitly returns null, which violates the declared string return type - PHP throws TypeError.',
                'tests' => [],
                'hints' => [
                    'The body returns nothing at all.',
                    'Nothing means null under the hood.',
                    'null does not satisfy string, so PHP throws TypeError.',
                ],
            ],
        ],

        'ch3-inheritance' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'inheritance',
                'prompt' => "Write a class Animal with a method speak(): string returning '...', then write a class Dog that extends Animal (empty body is fine).",
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Animal\n{\n    public function speak(): string\n    {\n        return '...';\n    }\n}\n\nclass Dog extends Animal\n{\n}\n",
                'explanation' => 'extends makes Dog inherit everything Animal declares.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'class\s+Animal'], 1],
                    ['assert_regex', ['pattern' => 'function\s+speak\s*\(\s*\)\s*:\s*string'], 1],
                    ['assert_contains', ['needle' => 'class Dog extends Animal'], 3],
                ],
                'hints' => [
                    'Two class declarations are needed.',
                    'speak() belongs to Animal.',
                    'Dog uses the extends keyword followed by Animal.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'inheritance',
                'prompt' => "What does extends give a class?\n\nA) The parent's methods and properties, overridable\nB) Only static methods\nC) A copy of the parent's source code\nD) Automatic interfaces",
                'starter' => '',
                'solution' => 'A',
                'explanation' => 'A subclass inherits the parent members and may override non-private ones.',
                'tests' => [],
                'hints' => [
                    'Inheritance is about reuse at runtime, not copied files.',
                    'Child classes can redefine most parent members.',
                    'The answer is A.',
                ],
            ],
        ],

        'ch3-the-inheritance-problem' => [
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'polymorphism',
                'prompt' => "Why can a PHP class not extend two parent classes?\n\nA) PHP is too slow\nB) The diamond problem: ambiguous inherited state and method resolution\nC) Namespaces forbid it\nD) Interfaces already do it",
                'starter' => '',
                'solution' => 'B',
                'explanation' => 'Multiple inheritance would let two parents contribute conflicting members; PHP keeps single inheritance and offers traits/interfaces instead.',
                'tests' => [],
                'hints' => [
                    'Picture a diamond-shaped family tree of classes.',
                    'Two parents could both define the same method.',
                    'The answer is B.',
                ],
            ],
            [
                'type' => 'explain',
                'difficulty' => 'medium',
                'concept' => 'polymorphism',
                'prompt' => 'Explain how PHP avoids the multiple-inheritance problem while still sharing behaviour between unrelated classes. Keywords graded: single inheritance, interface, trait.',
                'starter' => '',
                'solution' => 'PHP keeps single inheritance for state and implementation. It shares pure contracts through interfaces, and it shares reusable implementation through traits, which can be composed into any class.',
                'explanation' => 'Single inheritance keeps resolution unambiguous; interfaces define contracts and traits mix in implementation.',
                'tests' => [],
                'hints' => [
                    'One parent for state and code.',
                    'Contracts without implementation have their own keyword.',
                    'Implementation reuse without inheritance uses traits.',
                ],
            ],
        ],

        'ch3-working-with-inheritance' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'inheritance',
                'prompt' => "Given class Animal with speak(): string, write a subclass Dog that overrides speak() and starts its return value with the parent's result by calling parent::speak().",
                'starter' => "<?php\n\nclass Animal\n{\n    public function speak(): string\n    {\n        return '...';\n    }\n}\n\n// define Dog here\n",
                'solution' => "<?php\n\nclass Animal\n{\n    public function speak(): string\n    {\n        return '...';\n    }\n}\n\nclass Dog extends Animal\n{\n    public function speak(): string\n    {\n        return parent::speak().' Woof';\n    }\n}\n",
                'explanation' => 'parent:: reaches the overridden implementation so the child can extend it rather than replace it.',
                'tests' => [
                    ['assert_contains', ['needle' => 'class Dog extends Animal'], 2],
                    ['assert_regex', ['pattern' => 'function\s+speak\s*\(\s*\)\s*:\s*string'], 1],
                    ['assert_contains', ['needle' => 'parent::speak()'], 3],
                ],
                'hints' => [
                    'Dog must extend Animal first.',
                    'Override with the same signature.',
                    'Call the parent implementation with parent::speak().',
                ],
            ],
            [
                'type' => 'fix',
                'difficulty' => 'medium',
                'concept' => 'inheritance',
                'prompt' => "This child class is fatal: 'Declaration of Dog::speak(): int must be compatible with Animal::speak(): string'. Fix the child so the signatures match.\n\n```php\nclass Animal\n{\n    public function speak(): string { return '...'; }\n}\n\nclass Dog extends Animal\n{\n    public function speak(): int { return 1; }\n}\n```",
                'starter' => "<?php\n\nclass Animal\n{\n    public function speak(): string { return '...'; }\n}\n\nclass Dog extends Animal\n{\n    public function speak(): int { return 1; }\n}\n",
                'solution' => "<?php\n\nclass Animal\n{\n    public function speak(): string { return '...'; }\n}\n\nclass Dog extends Animal\n{\n    public function speak(): string { return 'Woof'; }\n}\n",
                'explanation' => 'An override must be compatible: same or narrower parameter types, and a covariant return - string stays string here.',
                'tests' => [
                    ['static_check', ['required' => ['function\s+speak\s*\(\s*\)\s*:\s*string']], 3],
                    ['static_check', ['banned' => ['function\s+speak\s*\(\s*\)\s*:\s*int']], 2],
                ],
                'hints' => [
                    'Read the error: which return type does the parent declare?',
                    'The child must not widen the contract.',
                    'Change : int to : string (and return a string).',
                ],
            ],
        ],

        'ch3-public-private-and-protected' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'visibility',
                'prompt' => 'Write a class Account with a private property $balance, a public method deposit(int $amount) that adds to the balance (initialise the balance to 0), and a public getter balance(): int.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Account\n{\n    private int \$balance = 0;\n\n    public function deposit(int \$amount): void\n    {\n        \$this->balance += \$amount;\n    }\n\n    public function balance(): int\n    {\n        return \$this->balance;\n    }\n}\n",
                'explanation' => 'State stays private; the public methods are the controlled door in and out.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'private\s+int\s+\$balance'], 3],
                    ['assert_regex', ['pattern' => 'public\s+function\s+deposit\s*\(\s*int\s+\$amount\s*\)'], 1],
                    ['assert_regex', ['pattern' => 'public\s+function\s+balance\s*\(\s*\)\s*:\s*int'], 1],
                ],
                'hints' => [
                    'The property must not be reachable from outside.',
                    'deposit() is the public entry point that mutates state.',
                    'balance() just returns $this->balance.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'concept' => 'visibility',
                'prompt' => "Which visibility lets a child class access a member, but not unrelated code?\n\nA) public\nB) private\nC) protected\nD) static",
                'starter' => '',
                'solution' => 'C',
                'explanation' => 'protected is exactly the middle rung: class + subclasses, nobody else.',
                'tests' => [],
                'hints' => [
                    'It sits between public and private.',
                    'Subclasses are allowed; siblings outside the family are not.',
                    'The answer is C.',
                ],
            ],
        ],

        'ch3-typed-properties' => [
            [
                'type' => 'write',
                'difficulty' => 'medium',
                'concept' => 'typed_properties',
                'prompt' => 'Write a class Point with typed properties: private int $x = 0 and private int $y = 0, plus a public method moveTo(int $x, int $y): void that assigns both.',
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Point\n{\n    private int \$x = 0;\n    private int \$y = 0;\n\n    public function moveTo(int \$x, int \$y): void\n    {\n        \$this->x = \$x;\n        \$this->y = \$y;\n    }\n}\n",
                'explanation' => 'The type lives on the property declaration, so every assignment is checked - no validating setter needed.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'private\s+int\s+\$x\s*=\s*0'], 2],
                    ['assert_regex', ['pattern' => 'private\s+int\s+\$y\s*=\s*0'], 2],
                    ['assert_regex', ['pattern' => 'function\s+moveTo\s*\(\s*int\s+\$x\s*,\s*int\s+\$y\s*\)\s*:\s*void'], 2],
                ],
                'hints' => [
                    'Both properties carry int and a default of 0.',
                    'moveTo takes the two new coordinates as ints.',
                    'Assign through $this->x and $this->y.',
                ],
            ],
            [
                'type' => 'predict_output',
                'difficulty' => 'hard',
                'concept' => 'typed_properties',
                'prompt' => "What happens?\n\n```php\nclass Config\n{\n    public int \$retries;\n}\n\n\$c = new Config;\necho \$c->retries;\n```\n\nAnswer with the class name of the thrown error.",
                'starter' => '',
                'solution' => 'Error',
                'explanation' => 'A typed property with no default is uninitialized, not null - reading it throws Error (UninitializedPropertyError subclasses Error).',
                'tests' => [],
                'hints' => [
                    'There is no default value on the property.',
                    'Typed properties are not silently null.',
                    'Reading one that was never initialised throws an Error.',
                ],
            ],
        ],

        'ch3-summary' => [
            [
                'type' => 'write',
                'difficulty' => 'hard',
                'concept' => 'class',
                'prompt' => "Build one class that uses the whole chapter: class Basket with a promoted private string \$owner, a promoted private float \$total = 0.0, a public method add(float \$amount): void that adds to the total, and a public method summary(): string that returns 'owner: ' followed by the owner.",
                'starter' => "<?php\n\n",
                'solution' => "<?php\n\nclass Basket\n{\n    public function __construct(\n        private string \$owner,\n        private float \$total = 0.0,\n    ) {}\n\n    public function add(float \$amount): void\n    {\n        \$this->total += \$amount;\n    }\n\n    public function summary(): string\n    {\n        return 'owner: '.\$this->owner;\n    }\n}\n",
                'explanation' => 'Promotion for state, types for the contract, methods for behaviour - the chapter in one class.',
                'tests' => [
                    ['assert_regex', ['pattern' => 'class\s+Basket'], 1],
                    ['assert_regex', ['pattern' => 'private\s+string\s+\$owner'], 2],
                    ['assert_regex', ['pattern' => 'function\s+add\s*\(\s*float\s+\$amount\s*\)\s*:\s*void'], 2],
                    ['assert_contains', ['needle' => "'owner: '"], 2],
                ],
                'hints' => [
                    'Both fields come from the constructor.',
                    'Promotion puts private on each constructor parameter.',
                    'summary() concatenates the literal with $this->owner.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'concept' => 'visibility',
                'prompt' => "Which chapter tool enforces data shape at assignment time?\n\nA) Inheritance\nB) Type declarations (properties, parameters, returns)\nC) Visibility\nD) Named arguments",
                'starter' => '',
                'solution' => 'B',
                'explanation' => 'Types are checked on every assignment and call; visibility only controls who may reach the member.',
                'tests' => [],
                'hints' => [
                    'The question says assignment time.',
                    'One tool validates values, the other controls access.',
                    'The answer is B.',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::$exercises as $slug => $exercises) {
            $lesson = Lesson::query()->where('slug', $slug)->first();

            if ($lesson === null) {
                Log::warning('ExerciseSeeder: lesson '.$slug.' not found - skipped.');

                continue;
            }

            foreach ($exercises as $index => $data) {
                $exercise = Exercise::query()->updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'ord' => $index + 1,
                    ],
                    [
                        'concept_id' => $this->conceptId($data['concept']),
                        'type' => $data['type'],
                        'difficulty' => $data['difficulty'],
                        'prompt' => $data['prompt'],
                        'starter_code' => $data['starter'],
                        'solution_code' => $data['solution'],
                        'explanation' => $data['explanation'],
                        'expected_answer' => $this->expectedAnswer($data),
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

                foreach ($data['hints'] as $hintIndex => $text) {
                    $exercise->hints()->updateOrCreate(
                        ['level' => $hintIndex + 1],
                        ['text' => $text],
                    );
                }

                $exercise->tests()->delete();

                foreach ($data['tests'] as $testIndex => [$type, $payload, $weight]) {
                    $exercise->tests()->create([
                        'ord' => $testIndex + 1,
                        'type' => $type,
                        'payload' => $payload,
                        'weight' => $weight,
                    ]);
                }
            }
        }
    }

    private function conceptId(?string $slug): ?int
    {
        if ($slug === null) {
            return null;
        }

        $id = Concept::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Stored-answer shape per exercise type (docs/04 section 5).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function expectedAnswer(array $data): ?array
    {
        return match ($data['type']) {
            'mcq', 'predict_output', 'find_error' => ['answer' => $data['solution']],
            'explain' => ['keywords' => $this->keywords($data['prompt'])],
            default => null,
        };
    }

    /**
     * Extract the graded keywords from the "Keywords graded: a, b, c" line.
     *
     * @return list<string>
     */
    private function keywords(string $prompt): array
    {
        if (! str_contains($prompt, 'Keywords graded:')) {
            return [];
        }

        $line = substr($prompt, strpos($prompt, 'Keywords graded:') + strlen('Keywords graded:'));
        $line = explode('.', $line)[0];

        return array_values(array_filter(array_map('trim', explode(',', $line))));
    }
}
