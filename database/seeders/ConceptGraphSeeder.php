<?php

namespace Database\Seeders;

use App\Enums\ContentSource;
use App\Enums\ContentStatus;
use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * The concept graph: ~100 concepts (Stage 0 foundations + book curriculum),
 * prerequisite edges transcribed from docs/05-knowledge-map.md section 1,
 * and lesson_concepts links for every Chapter 3 lesson.
 *
 * Stage 0 has no lessons yet, so its concepts exist without lesson links
 * until the AI pipeline (Phase 7) generates them.
 */
class ConceptGraphSeeder extends Seeder
{
    /**
     * @param  string  $slug
     * @param  string  $name
     * @param  string  $domain  one of the 16 skill domains (docs/04 section 9)
     * @param  string  $granularity  topic|concept|detail
     * @param  bool  $core  participates in stage gates
     * @param  string  $definition
     *
     * @var list<array{string, string, string, string, bool, string}>
     */
    private const CONCEPTS = [
        // --- Knowledge map graph: foundations (Stage 0) ---
        ['variables', 'Variables', 'variables', 'topic', false, 'Named storage for a value; PHP infers the type from what you assign.'],
        ['conditions', 'Conditions', 'conditions', 'topic', false, 'Branching a program with if, elseif, else and match.'],
        ['loops', 'Loops', 'loops', 'topic', false, 'Repeating a block with for, while and foreach, steered by break and continue.'],
        ['arrays', 'Arrays', 'arrays', 'topic', false, 'Ordered maps: one structure for both lists and key=>value collections.'],
        ['strings', 'Strings', 'strings', 'topic', false, 'Text values with interpolation, heredoc syntax and a rich function set.'],
        ['functions', 'Functions', 'functions', 'topic', false, 'Reusable blocks of code that take arguments and return values.'],
        ['superglobals', 'Superglobals', 'variables', 'concept', false, 'Preset variables such as $_GET, $_POST and $_SERVER, available everywhere.'],
        ['scope', 'Scope', 'variables', 'concept', false, 'Where a variable is visible: global, local, static and closure scope.'],
        ['references', 'References', 'variables', 'concept', false, 'Two variables sharing one value slot, created with =&.'],
        ['operators', 'Operators', 'conditions', 'concept', false, 'Comparison, logical and arithmetic symbols that form expressions.'],
        ['match_expression', 'match', 'conditions', 'concept', false, "PHP 8's strict, expression form of switch that always returns a value."],
        ['null_safe_operator', 'Null Safe Operator', 'conditions', 'concept', false, '?-> short-circuits an entire chain when the receiver is null.'],
        ['foreach', 'foreach', 'loops', 'concept', false, 'The default loop for walking arrays and Traversable objects.'],
        ['iterators', 'Iterators', 'loops', 'concept', false, 'Objects implementing Iterator that yield their values one at a time.'],
        ['indexed_arrays', 'Indexed Arrays', 'arrays', 'concept', false, 'Lists keyed by integers, kept in insertion order.'],
        ['associative_arrays', 'Associative Arrays', 'arrays', 'concept', false, 'Maps keyed by strings or integers.'],
        ['spl', 'SPL Structures', 'arrays', 'concept', false, "PHP's built-in data structures and interfaces such as ArrayObject and SplStack."],
        ['interpolation', 'String Interpolation', 'strings', 'concept', false, 'Embedding variables inside double-quoted strings and heredocs.'],
        ['heredoc', 'Heredoc & Nowdoc', 'strings', 'concept', false, 'Multi-line string literals; nowdoc skips interpolation entirely.'],
        ['constants', 'Constants', 'php_syntax', 'concept', false, 'Immutable named values declared with const or define().'],
        ['closures', 'Closures', 'functions', 'concept', false, 'Anonymous functions that capture variables from their surroundings.'],
        ['arrow_functions', 'Arrow Functions', 'functions', 'concept', false, 'fn() => expressions: closures with automatic by-value capture.'],
        ['callbacks', 'Callbacks', 'functions', 'concept', false, 'Functions handed to other functions, as in array_map and usort.'],
        ['generators', 'Generators', 'functions', 'concept', false, 'Functions that yield values lazily instead of returning a whole array.'],
        ['include_require', 'Include & Require', 'files', 'concept', false, 'Loading PHP files at runtime; require_once guards against re-declaration.'],
        ['streams', 'Streams', 'files', 'concept', false, 'Uniform read/write access to files, sockets and filtering pipelines.'],
        ['request_response', 'Request & Response', 'http', 'concept', false, 'The inputs of an HTTP request and the response a script produces.'],
        ['sessions', 'Sessions', 'http', 'concept', false, 'Persisting per-user state across requests on the server.'],
        ['cookies', 'Cookies', 'http', 'concept', false, 'Small pieces of data the browser stores and sends with every request.'],
        ['cli_tools', 'CLI Tools', 'php_syntax', 'topic', false, 'Running PHP from the terminal: plain scripts, Composer scripts and REPL tools.'],
        ['error_handling', 'Error Handling', 'exceptions', 'concept', false, 'Responding to PHP errors and warnings without letting the request die.'],
        ['error_patterns', 'Error Patterns', 'exceptions', 'concept', false, 'Repeatable strategies: fail fast, wrap and rethrow, aggregate and report.'],
        ['pdo', 'PDO', 'database', 'concept', false, "PHP's database access layer with prepared statements and transactions."],
        ['password_hashing', 'Password Hashing', 'security', 'concept', false, 'Hashing with bcrypt or argon2 and checking with password_verify().'],
        ['input_validation', 'Input Validation', 'security', 'concept', false, 'Rejecting unexpected input at the boundary before it is ever used.'],
        ['escaping', 'Escaping', 'security', 'concept', false, 'Encoding output for its context so user data cannot inject markup or SQL.'],
        ['auth_flows', 'Auth Flows', 'security', 'concept', false, 'Registration, login, sessions and guards working as one coherent flow.'],
        ['composer2', 'Composer', 'modern_php', 'concept', false, 'Dependency manager: composer.json, lock file, scripts and package discovery.'],
        ['static_analysis', 'Static Analysis', 'modern_php', 'concept', false, 'Finding type and logic bugs without running the code, with PHPStan or Psalm.'],
        ['psr', 'PSR-1/12/4', 'modern_php', 'concept', false, 'PHP standards for coding style, file structure and autoloading.'],
        ['phpunit', 'PHPUnit', 'testing', 'concept', false, 'The standard unit-testing framework for PHP.'],
        ['assertions', 'Assertions', 'testing', 'concept', false, 'Statements that declare what must be true about your code.'],
        ['mocks', 'Mocks', 'testing', 'concept', false, 'Test doubles standing in for collaborators you do not want to execute.'],
        ['tdd', 'Test-Driven Development', 'testing', 'concept', false, 'Writing the failing test first, then the smallest change that passes.'],
        ['enums', 'Enumerations', 'modern_php', 'concept', false, 'Pure PHP enumerations of fixed values, optionally with methods and backing values.'],
        ['readonly', 'readonly', 'modern_php', 'concept', false, 'Properties that may be initialised once and never reassigned.'],
        ['property_hooks', 'Property Hooks', 'modern_php', 'concept', false, 'Intercepting reads and writes of a property with hooked accessors.'],
        ['fibers', 'Fibers', 'modern_php', 'concept', false, 'Independent call stacks that suspend and resume for cooperative concurrency.'],
        ['first_class_callables', 'First-Class Callables', 'modern_php', 'concept', false, 'The fn(...) syntax that turns any function into a closure on the spot.'],
        ['type_declarations', 'Type Declarations', 'data_types', 'concept', false, 'Parameter and return types that constrain what a function accepts and returns.'],
        ['primitive_types', 'Primitive Types', 'data_types', 'concept', false, "int, float, string and bool: PHP's scalar building blocks."],
        ['union_types', 'Union Types', 'data_types', 'concept', false, 'Types accepting several alternatives, such as int|string.'],
        ['nullable_types', 'Nullable Types', 'data_types', 'concept', false, 'Types that explicitly allow null, written as ?type or type|null.'],
        ['mixed', 'mixed', 'data_types', 'concept', false, 'The escape-hatch type that accepts any value.'],
        ['type_checking', 'Type-Checking Functions', 'data_types', 'concept', false, 'is_*, gettype and friends that inspect a value type at runtime.'],
        ['type_coercion', 'Type Coercion', 'data_types', 'concept', false, 'PHP converting a value between types, silently unless strict_types is declared.'],
        ['return_types', 'Return Types', 'data_types', 'concept', false, "The declared type of a function's return value."],
        ['typed_properties', 'Typed Properties', 'variables', 'concept', false, 'Declaring property types so invalid object state is rejected early.'],
        ['dynamic_properties', 'Dynamic Properties', 'oop', 'concept', false, 'Creating properties by assignment instead of declaration; deprecated since PHP 8.2.'],
        ['magic_methods', 'Magic Methods', 'oop', 'concept', false, '__construct, __get, __toString and friends that the engine invokes for you.'],
        ['named_arguments', 'Named Arguments', 'php_syntax', 'concept', false, 'Passing arguments by parameter name, in any order, for readable calls.'],
    ];

    /**
     * Book curriculum + design vocabulary (knowledge map graph nodes).
     *
     * @var list<array{string, string, string, string, bool, string}>
     */
    private const CURRICULUM = [
        ['exception', 'Errors & Exceptions', 'exceptions', 'topic', true, "PHP's error and exception system: throw, catch, and the engine's own failures."],
        ['try_catch', 'Try/Catch/Finally', 'exceptions', 'concept', false, 'Capturing thrown exceptions safely, with a guaranteed finally block.'],
        ['class', 'Classes & Objects', 'oop', 'concept', true, 'A class defines state and behaviour; objects are instances of that definition.'],
        ['properties', 'Properties & Methods', 'oop', 'concept', false, 'Class state held in properties, and behaviour implemented as methods.'],
        ['constructor', 'Constructor', 'oop', 'concept', false, '__construct initialises each new object; promoted parameters collapse the boilerplate.'],
        ['constructor_promotion', 'Constructor Property Promotion', 'oop', 'concept', false, 'Declaring constructor parameters as properties in a single step (PHP 8).'],
        ['object_identity', 'Object Identity', 'oop', 'concept', false, '=== compares instance identity: two objects with equal fields are still distinct.'],
        ['inheritance', 'Inheritance', 'oop', 'concept', true, 'Deriving a class from a base class to reuse and specialise its behaviour.'],
        ['visibility', 'Visibility & Encapsulation', 'oop', 'concept', false, 'public, protected and private control who may read or write a member.'],
        ['encapsulation', 'Encapsulation', 'oop', 'concept', true, 'Bundling state with the methods that touch it, and hiding the rest.'],
        ['polymorphism', 'Polymorphism', 'oop', 'concept', false, 'Different classes responding to the same call each in their own way.'],
        ['abstract_class', 'Abstract Classes', 'oop', 'concept', false, 'A partial base class that forces subclasses to complete certain methods.'],
        ['interface', 'Interfaces', 'oop', 'concept', true, 'A pure contract of method signatures with no implementation.'],
        ['trait', 'Traits', 'oop', 'concept', true, 'A bundle of methods mixed into classes to share behaviour horizontally.'],
        ['static', 'Static & Constants', 'oop', 'concept', false, 'Members belonging to the class itself rather than to any one instance.'],
        ['namespace', 'Namespaces', 'php_syntax', 'concept', true, 'Named scopes that prevent class-name collisions and organise code.'],
        ['autoloading', 'Autoloading & Composer', 'modern_php', 'concept', true, 'Loading classes on demand by PSR-4 rules; Composer wires it up.'],
        ['attributes', 'Attributes', 'modern_php', 'concept', false, 'Structured metadata attached to classes and members, readable at runtime.'],
        ['reflection', 'Reflection', 'oop', 'concept', false, 'Inspecting classes, methods and properties from running code.'],
        ['composition', 'Composition', 'oop', 'concept', true, 'Building classes from other objects instead of inheriting from them.'],
        ['coupling', 'Coupling', 'oop', 'concept', true, "How much one class leans on another's internals; good design keeps it low."],
        ['cohesion', 'Cohesion', 'oop', 'concept', false, 'How focused one class is; high cohesion means a single clear job.'],
        ['design_principles', 'Design Principles', 'oop', 'topic', false, 'Guidelines such as SOLID that keep object designs flexible.'],
        ['uml', 'UML Class & Sequence', 'oop', 'topic', false, 'Standard diagrams for static structure and runtime interaction.'],
        ['pattern_principles', 'Pattern Principles', 'oop', 'topic', false, 'What design patterns are, how to describe them, and when one fits.'],
        ['creational', 'Creational Patterns', 'oop', 'topic', false, 'Patterns that control how objects come into being.'],
        ['structural', 'Structural Patterns', 'oop', 'topic', false, 'Patterns that compose classes and objects into larger structures.'],
        ['behavioral', 'Behavioral Patterns', 'oop', 'topic', false, 'Patterns that distribute responsibility and coordinate behaviour.'],
        ['factory', 'Factory Pattern', 'oop', 'concept', true, 'Creating objects through a dedicated creator instead of new at every call site.'],
        ['strategy', 'Strategy Pattern', 'oop', 'concept', true, 'Swapping an algorithm behind a common interface at runtime.'],
        ['di', 'Dependency Injection', 'oop', 'concept', true, 'Receiving collaborators from outside instead of constructing them internally.'],
        ['enterprise', 'Enterprise Patterns', 'oop', 'topic', false, 'Patterns for service layers, domain objects and application architecture.'],
        ['persistence', 'Persistence Patterns', 'database', 'topic', false, 'Patterns that map objects to storage: data mapper, identity map, unit of work.'],
        ['data_mapper', 'Data Mapper', 'database', 'concept', false, 'A layer that moves objects to and from storage without the object knowing SQL.'],
        ['identity_map', 'Identity Map', 'database', 'concept', false, 'One object per row per request, so repeated reads return the same instance.'],
        ['unit_of_work', 'Unit of Work', 'database', 'concept', false, 'Tracking changes to many objects and writing them in a single transaction.'],
        ['transactions', 'Transactions', 'database', 'concept', false, 'Atomic groups of writes that commit together or not at all.'],
        ['api', 'HTTP & REST', 'http', 'concept', false, 'Designing HTTP endpoints as resources with predictable methods and status codes.'],
        ['ci', 'Git, CI & Build', 'testing', 'topic', false, 'Version control plus automated checks that run on every change.'],
        ['capstone', 'Capstone', 'oop', 'topic', false, 'The final project combining patterns, tests and delivery.'],
        ['laravel', 'Laravel Bridge', 'modern_php', 'topic', false, 'How each core PHP pattern from the book shows up in Laravel.'],
    ];

    /**
     * Prerequisite edges from docs/05-knowledge-map.md section 1.
     * [prereq slug, concept slug, weight] — weight 0 = soft "used later" edge.
     *
     * @var list<array{string, string, int}>
     */
    private const EDGES = [
        ['variables', 'conditions', 1],
        ['variables', 'loops', 1],
        ['variables', 'arrays', 1],
        ['variables', 'strings', 1],
        ['conditions', 'functions', 1],
        ['loops', 'functions', 1],
        ['arrays', 'functions', 1],
        ['strings', 'functions', 1],
        ['functions', 'exception', 1],
        ['functions', 'class', 1],
        ['class', 'properties', 1],
        ['properties', 'inheritance', 1],
        ['properties', 'visibility', 1],
        ['inheritance', 'polymorphism', 1],
        ['inheritance', 'abstract_class', 1],
        ['class', 'interface', 1],
        ['class', 'trait', 1],
        ['class', 'static', 1],
        ['exception', 'try_catch', 1],
        ['class', 'namespace', 1],
        ['namespace', 'autoloading', 1],
        ['autoloading', 'attributes', 1],
        ['autoloading', 'reflection', 1],
        ['visibility', 'design_principles', 1],
        ['polymorphism', 'design_principles', 1],
        ['design_principles', 'uml', 1],
        ['design_principles', 'pattern_principles', 1],
        ['pattern_principles', 'creational', 1],
        ['pattern_principles', 'structural', 1],
        ['pattern_principles', 'behavioral', 1],
        ['creational', 'di', 1],
        ['di', 'enterprise', 1],
        ['structural', 'enterprise', 1],
        ['behavioral', 'enterprise', 1],
        ['enterprise', 'persistence', 1],
        ['autoloading', 'psr', 1],
        ['psr', 'phpunit', 1],
        ['phpunit', 'ci', 1],
        ['persistence', 'api', 1],
        ['ci', 'capstone', 1],
        ['api', 'capstone', 1],
        ['capstone', 'laravel', 1],
        ['di', 'laravel', 0],
        ['enterprise', 'laravel', 0],
        ['persistence', 'laravel', 0],
        // Curated additions for gate concepts not drawn in the knowledge map
        ['inheritance', 'composition', 1],
        ['visibility', 'encapsulation', 1],
        ['design_principles', 'coupling', 1],
        ['design_principles', 'cohesion', 1],
        ['creational', 'factory', 1],
        ['behavioral', 'strategy', 1],
    ];

    /**
     * lesson_concepts links for Chapter 3 (Stage 0 has no lessons yet).
     * lesson slug => [role => list of concept slugs]
     *
     * @var array<string, array<string, list<string>>>
     */
    private const LESSON_LINKS = [
        'ch3-classes-and-objects' => ['core' => ['class']],
        'ch3-a-first-class' => ['core' => ['class']],
        'ch3-a-first-object-or-two' => ['core' => ['class', 'object_identity']],
        'ch3-setting-properties-in-a-class' => ['core' => ['properties']],
        'ch3-working-with-methods' => ['core' => ['properties']],
        'ch3-creating-a-constructor-method' => ['core' => ['constructor']],
        'ch3-constructor-property-promotion' => ['core' => ['constructor', 'constructor_promotion'], 'built_on' => ['typed_properties']],
        'ch3-default-arguments-and-named-arguments' => ['core' => ['named_arguments'], 'prereq' => ['functions']],
        'ch3-arguments-and-types' => ['core' => ['type_coercion'], 'prereq' => ['functions']],
        'ch3-primitive-types' => ['core' => ['primitive_types'], 'prereq' => ['variables']],
        'ch3-some-other-type-checking-functions' => ['core' => ['type_checking'], 'prereq' => ['primitive_types']],
        'ch3-type-declarations-object-types' => ['core' => ['type_declarations'], 'built_on' => ['class']],
        'ch3-type-declarations-primitive-types' => ['core' => ['type_declarations']],
        'ch3-mixed-types' => ['core' => ['mixed'], 'prereq' => ['type_declarations']],
        'ch3-union-types' => ['core' => ['union_types'], 'prereq' => ['primitive_types']],
        'ch3-nullable-types' => ['core' => ['nullable_types'], 'prereq' => ['primitive_types']],
        'ch3-return-type-declarations' => ['core' => ['return_types'], 'prereq' => ['type_declarations']],
        'ch3-inheritance' => ['core' => ['inheritance'], 'prereq' => ['class']],
        'ch3-the-inheritance-problem' => ['core' => ['inheritance'], 'built_on' => ['polymorphism']],
        'ch3-working-with-inheritance' => ['core' => ['inheritance', 'polymorphism']],
        'ch3-public-private-and-protected' => ['core' => ['visibility'], 'built_on' => ['encapsulation']],
        'ch3-typed-properties' => ['core' => ['typed_properties'], 'prereq' => ['properties']],
        'ch3-summary' => ['core' => ['class', 'inheritance', 'visibility']],
    ];

    public function run(): void
    {
        $this->seedConcepts();
        $this->seedEdges();
        $this->seedLessonLinks();
    }

    private function seedConcepts(): void
    {
        foreach ([...self::CONCEPTS, ...self::CURRICULUM] as [$slug, $name, $domain, $granularity, $core, $definition]) {
            Concept::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'definition' => $definition,
                    'skill_domain' => $domain,
                    'granularity' => $granularity,
                    'is_core' => $core,
                    'source' => ContentSource::Ai,
                    'status' => ContentStatus::Published,
                ],
            );
        }
    }

    private function seedEdges(): void
    {
        foreach (self::EDGES as [$prereqSlug, $conceptSlug, $weight]) {
            $prereq = Concept::query()->where('slug', $prereqSlug)->first();
            $concept = Concept::query()->where('slug', $conceptSlug)->first();

            if ($prereq === null || $concept === null) {
                Log::warning('ConceptGraphSeeder: skipping edge, concept missing.', [
                    'prereq' => $prereqSlug,
                    'concept' => $conceptSlug,
                ]);

                continue;
            }

            ConceptPrerequisite::updateOrCreate(
                ['concept_id' => $concept->id, 'prereq_concept_id' => $prereq->id],
                ['weight' => $weight, 'source' => ContentSource::Manual],
            );
        }
    }

    private function seedLessonLinks(): void
    {
        foreach (self::LESSON_LINKS as $lessonSlug => $roles) {
            $lesson = Lesson::query()->where('slug', $lessonSlug)->first();

            if ($lesson === null) {
                Log::warning('ConceptGraphSeeder: skipping links, lesson missing.', ['lesson' => $lessonSlug]);

                continue;
            }

            foreach ($roles as $role => $conceptSlugs) {
                $pivot = [];

                foreach ($conceptSlugs as $conceptSlug) {
                    $concept = Concept::query()->where('slug', $conceptSlug)->first();

                    if ($concept === null) {
                        Log::warning('ConceptGraphSeeder: skipping link, concept missing.', [
                            'lesson' => $lessonSlug,
                            'concept' => $conceptSlug,
                        ]);

                        continue;
                    }

                    $pivot[$concept->id] = ['role' => $role];
                }

                $lesson->concepts()->syncWithoutDetaching($pivot);
            }
        }
    }
}
