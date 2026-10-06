<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProjectLevel;
use App\Enums\ProvenanceSource;
use App\Models\Project;
use App\Models\Stage;
use Illuminate\Database\Seeder;

/**
 * The project ladder (docs/15 Phase 9): 4 beginner, 4 intermediate,
 * 4 advanced, 1 capstone. Deterministic, idempotent (updateOrCreate on slug).
 * Task concept slugs are checked against ConceptGraphSeeder's vocabulary.
 *
 * Split across two constants: one larger literal exceeds PHPStan's array-shape
 * union limit, degrades to a general array and loses the task offset checks.
 */
class ProjectSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string, level: string, stage: int, ord: int, title: string,
     *     brief: string, requirements: list<string>, solution: string,
     *     tasks: list<array{brief: string, concepts: list<string>, hint: string}>
     * }>
     */
    private const FOUNDATION_PROJECTS = [
        // --- Beginner (Stage 0 concepts) ---
        [
            'slug' => 'cli-stats',
            'level' => 'beginner',
            'stage' => 0,
            'ord' => 1,
            'title' => 'CLI Stats',
            'brief' => "Read a list of numbers from command-line arguments and print count, min, max and average.\nYou will loop, branch and keep state in plain variables — the first real script shape.",
            'requirements' => [
                'Accept any number of numeric arguments',
                'Reject non-numeric input with a clear message',
                'Print count, min, max and average',
                'Exit with status 0 on success and 1 on bad input',
            ],
            'solution' => "Validate with is_numeric first, map the arguments with array_map, then hand the clean array to a single stats() function that returns an array of results.\nKeep parsing (input) and computing (logic) in separate functions so the compute half can be unit-tested without touching argv.",
            'tasks' => [
                ['brief' => 'Parse $argv into a clean list of floats, skipping the script name.', 'concepts' => ['variables', 'conditions'], 'hint' => 'array_slice($argv, 1) then is_numeric() in a foreach.'],
                ['brief' => 'Compute count, min, max and average from the clean list.', 'concepts' => ['loops', 'functions'], 'hint' => 'min()/max()/count() are built in; average is sum/count.'],
                ['brief' => 'Handle the empty and all-invalid cases with a friendly message.', 'concepts' => ['conditions'], 'hint' => 'Check the cleaned list before computing; return a non-zero exit code.'],
                ['brief' => 'Wrap the output in a format() function and print once.', 'concepts' => ['functions'], 'hint' => 'One function, one job — format() should not read globals.'],
            ],
        ],
        [
            'slug' => 'todo-txt',
            'level' => 'beginner',
            'stage' => 0,
            'ord' => 2,
            'title' => 'Todo List (text file)',
            'brief' => "A todo list that persists in a plain text file: add, list, done, clear.\nAssociative array state, file I/O at the edges, pure functions for the rules.",
            'requirements' => [
                'Persist todos between runs in a text file',
                'Commands: add <text>, list, done <n>, clear',
                'Re-number items on every list',
                'Never let a bad command corrupt the file',
            ],
            'solution' => "Load the file into an array of items, apply exactly one command to produce the next array, then save.\nOnly the load() and save() functions touch the filesystem — the command handlers stay pure and testable.",
            'tasks' => [
                ['brief' => 'Design the on-disk format and write load()/save() around it.', 'concepts' => ['arrays', 'functions'], 'hint' => 'One item per line; explode/PHP_EOL or json_encode for safety.'],
                ['brief' => 'Implement add and list against an in-memory array.', 'concepts' => ['associative_arrays', 'foreach'], 'hint' => 'array_push/[] to add; foreach with an index counter to number them.'],
                ['brief' => 'Implement done <n> and clear with bounds checks.', 'concepts' => ['conditions', 'scope'], 'hint' => 'Validate n against count() before touching the array.'],
                ['brief' => 'Route argv commands to handlers in a match expression.', 'concepts' => ['match_expression'], 'hint' => 'match ($command) { \'add\' => ..., default => usage() }.'],
            ],
        ],
        [
            'slug' => 'password-validator',
            'level' => 'beginner',
            'stage' => 0,
            'ord' => 3,
            'title' => 'Password Validator',
            'brief' => "Score passwords by length, character classes and common patterns.\nString inspection, strict comparison and clear result reporting.",
            'requirements' => [
                'Score: length, lower, upper, digit, symbol, common-password check',
                'Return a structured result, not echo from the core',
                'Reject common passwords regardless of score',
                'Cover the rules with assertions',
            ],
            'solution' => "Build one check per rule, each returning a boolean or small score, then fold the results into a verdict array.\nA dictionary of common passwords lives in a constant; the core function never prints — the CLI wrapper does.",
            'tasks' => [
                ['brief' => 'Implement each character-class check with preg_match or str_contains.', 'concepts' => ['strings', 'type_checking'], 'hint' => 'preg_match(\'/[A-Z]/\', $p) === 1 for the upper-case class.'],
                ['brief' => 'Combine checks into a 0-5 score with a verdict word.', 'concepts' => ['functions', 'conditions'], 'hint' => 'match on the score for weak/fair/strong.'],
                ['brief' => 'Add a common-password deny list.', 'concepts' => ['arrays', 'type_checking'], 'hint' => 'in_array(strtolower($p), self::COMMON, true).'],
                ['brief' => 'Write a tiny assertion script proving the edge cases.', 'concepts' => ['functions'], 'hint' => 'assert() with === — empty, all-symbol and common passwords.'],
            ],
        ],
        [
            'slug' => 'csv-report',
            'level' => 'beginner',
            'stage' => 0,
            'ord' => 4,
            'title' => 'CSV Report',
            'brief' => "Read a CSV of sales rows and print a per-region summary table.\nFile parsing, associative aggregation and formatted output.",
            'requirements' => [
                'Parse CSV with a header row into associative rows',
                'Group totals by region',
                'Align the output table',
                'Fail loudly on malformed rows',
            ],
            'solution' => "fgetcsv() per line into a row keyed by the header, then accumulate into a totals-by-region array.\nThe parser returns rows, the aggregator returns totals, the formatter prints — three functions, one direction of data flow.",
            'tasks' => [
                ['brief' => 'Read the CSV into a list of associative rows.', 'concepts' => ['include_require', 'associative_arrays'], 'hint' => 'fgetcsv() after fgetcsv() for the header, array_combine them.'],
                ['brief' => 'Aggregate totals per region.', 'concepts' => ['loops'], 'hint' => '$totals[$row[\'region\']] = ($totals[...] ?? 0) + $amount.'],
                ['brief' => 'Format an aligned table with sprintf.', 'concepts' => ['strings'], 'hint' => 'sprintf(\'%-12s %10.2f\', $region, $total) per column.'],
                ['brief' => 'Throw on rows with the wrong column count.', 'concepts' => ['exception'], 'hint' => 'count($cells) !== count($header) → throw new RuntimeException(...).'],
            ],
        ],

        // --- Intermediate ---
        [
            'slug' => 'library-catalog',
            'level' => 'intermediate',
            'stage' => 2,
            'ord' => 5,
            'title' => 'Library Catalog',
            'brief' => "Model books and loans as objects: encapsulated state, invariants guarded by methods, catalogue composed of small parts.\nAn OOP redesign of the todo list.",
            'requirements' => [
                'Book and Loan value objects with guarded state',
                'Catalogue with add/find/loan/return',
                'Invariants: no double loans, no missing books',
                'Identity via ===, not ==',
            ],
            'solution' => "Book is immutable (readonly); Loan carries the mutable due date; Catalogue is the only object allowed to change the collection.\nExpose behaviour (loan()/return()) instead of setters so the invariants live in one place.",
            'tasks' => [
                ['brief' => 'Create a readonly Book with isbn, title and author.', 'concepts' => ['class', 'readonly', 'constructor_promotion'], 'hint' => 'public function __construct(private readonly string $isbn, ...) {}.'],
                ['brief' => 'Model Loan with a due date that only moves forward.', 'concepts' => ['properties', 'encapsulation'], 'hint' => 'Guard in a renew() method; private $dueDate, no public setter.'],
                ['brief' => 'Build Catalogue::loan()/return() enforcing no double loans.', 'concepts' => ['composition', 'conditions'], 'hint' => 'Track active loans keyed by isbn; throw if already present.'],
                ['brief' => 'Prove identity semantics in a small script.', 'concepts' => ['object_identity'], 'hint' => '$a === $b is false for two equal new Book(...).'],
            ],
        ],
        [
            'slug' => 'shape-renderer',
            'level' => 'intermediate',
            'stage' => 2,
            'ord' => 6,
            'title' => 'Shape Renderer',
            'brief' => "Draw shapes through an interface: Circle, Rectangle and Triangle render themselves, and a renderer walks any list of shapes.\nPolymorphism over conditionals.",
            'requirements' => [
                'Renderable interface with area() and render()',
                'Three concrete shapes with their own maths',
                'Renderer works on any Renderable list',
                'No instanceof chains in the renderer',
            ],
            'solution' => "Each shape owns its maths and its SVG fragment; the renderer only iterates and concatenates.\nAdding a Pentagon later means one new class — zero edits to the renderer (open for extension, closed for modification).",
            'tasks' => [
                ['brief' => 'Define the Renderable interface (area(): float, render(): string).', 'concepts' => ['interface'], 'hint' => 'interface Renderable { public function area(): float; public function render(): string; }'],
                ['brief' => 'Implement Circle and Rectangle against it.', 'concepts' => ['class', 'properties'], 'hint' => 'Constructor promotion for radius/width/height.'],
                ['brief' => 'Add Triangle with its own area() formula.', 'concepts' => ['polymorphism'], 'hint' => '0.5 * base * height; no shared base class needed.'],
                ['brief' => 'Write Renderer::toSvg(array $shapes) with no type checks.', 'concepts' => ['abstract_class', 'foreach'], 'hint' => 'A docblock list<Renderable> plus a foreach — the interface does the checking.'],
            ],
        ],
        [
            'slug' => 'notification-hub',
            'level' => 'intermediate',
            'stage' => 7,
            'ord' => 7,
            'title' => 'Notification Hub',
            'brief' => "Send the same message through email, SMS and log channels by swapping a Strategy at runtime.\nInterfaces, DI and composition instead of if/else forests.",
            'requirements' => [
                'Sender interface with one send(message, recipient) method',
                'Three interchangeable strategies',
                'Hub accepts the strategy by constructor injection',
                'Channels can be combined (fan-out) without new code',
            ],
            'solution' => "The hub depends on the Sender interface only; each channel implements it and owns its transport.\nFan-out is just a Composite sender wrapping a list of strategies — the hub never learns about the concrete channels.",
            'tasks' => [
                ['brief' => 'Define the Sender interface and three fake channels.', 'concepts' => ['interface', 'strategy'], 'hint' => 'EmailSender implements Sender; log the payload instead of really sending.'],
                ['brief' => 'Inject the strategy into NotificationHub via the constructor.', 'concepts' => ['di'], 'hint' => 'public function __construct(private Sender $sender) {}.'],
                ['brief' => 'Swap strategies at runtime from a config array.', 'concepts' => ['factory', 'match_expression'], 'hint' => 'match ($config[\'channel\']) { \'sms\' => new SmsSender(), ... }.'],
                ['brief' => 'Add a FanOutSender that loops its children.', 'concepts' => ['composition'], 'hint' => 'implements Sender, holds list<Sender>, send() delegates to each.'],
            ],
        ],
        [
            'slug' => 'plugin-registry',
            'level' => 'intermediate',
            'stage' => 5,
            'ord' => 8,
            'title' => 'Plugin Registry',
            'brief' => "Register plugins by attribute, look them up by name and run them through a shared contract.\nFactory creation plus traits for shared plugin plumbing.",
            'requirements' => [
                'Attribute marks a class as a plugin with a name',
                'Registry builds instances through one factory path',
                'Shared logging behaviour lives in a trait',
                'Unknown plugin names fail fast',
            ],
            'solution' => "A #[Plugin(name: ...)] attribute is the single source of truth for registration; reflection reads it at boot.\nThe factory is the only place that calls new — swapping to lazy or lazy-proxied instances changes one method.",
            'tasks' => [
                ['brief' => 'Create the Plugin interface and the #[Plugin] attribute.', 'concepts' => ['interface', 'attributes'], 'hint' => '#[Attribute(Attribute::TARGET_CLASS)] final class Plugin { public function __construct(public string $name) {} }.'],
                ['brief' => 'Write Registry::register()/get() with fail-fast lookups.', 'concepts' => ['static', 'conditions'], 'hint' => 'array keyed by name; get() throws InvalidArgumentException when missing.'],
                ['brief' => 'Move shared logging into a PluginLogging trait.', 'concepts' => ['trait'], 'hint' => 'use PluginLogging inside two plugin classes.'],
                ['brief' => 'Build the factory path that instantiates from class names.', 'concepts' => ['factory', 'creational'], 'hint' => 'new $class(...) behind one make(string $class) method.'],
            ],
        ],

    ];

    /**
     * @var list<array{
     *     slug: string, level: string, stage: int, ord: int, title: string,
     *     brief: string, requirements: list<string>, solution: string,
     *     tasks: list<array{brief: string, concepts: list<string>, hint: string}>
     * }>
     */
    private const ADVANCED_PROJECTS = [
        // --- Advanced ---
        [
            'slug' => 'mini-router',
            'level' => 'advanced',
            'stage' => 8,
            'ord' => 9,
            'title' => 'Mini Router (front controller)',
            'brief' => "One public/index.php dispatching GET and POST routes to handlers — the book's front controller and page controller patterns, from scratch.",
            'requirements' => [
                'Single front controller: all requests enter one script',
                'GET/POST route tables with path params (/user/{id})',
                '404 and 405 handled deliberately',
                'Handlers stay testable without a web server',
            ],
            'solution' => "index.php boots the Router, matches the request, and hands off — everything else lives in classes under a namespace.\nSplit matching (pure string work) from dispatch (side effects) so the matcher is unit-testable with plain strings.",
            'tasks' => [
                ['brief' => 'Build the front controller bootstrap in public/index.php.', 'concepts' => ['request_response', 'namespace'], 'hint' => 'require the autoloader, build $_SERVER-based Request, dispatch, echo Response.'],
                ['brief' => 'Implement route matching with {param} segments.', 'concepts' => ['autoloading', 'psr'], 'hint' => 'preg_quote the pattern, replace {x} with a named capture group.'],
                ['brief' => 'Dispatch handlers with dependency injection by hand.', 'concepts' => ['di'], 'hint' => 'call_user_func_array with the captured params.'],
                ['brief' => 'Return 404/405 as structured responses, not echoes.', 'concepts' => ['request_response'], 'hint' => 'Response value object with status + body; index.php prints it once.'],
            ],
        ],
        [
            'slug' => 'data-mapper-notes',
            'level' => 'advanced',
            'stage' => 8,
            'ord' => 10,
            'title' => 'Notes with a Data Mapper',
            'brief' => "Persist Note objects through a hand-written data mapper over PDO, with identity map and unit-of-work style batching.\nThe book's persistence chapter, implemented.",
            'requirements' => [
                'Note domain object with no SQL inside',
                'Data mapper: hydrate to objects, persist from objects',
                'Identity map returns the same instance per row',
                'Writes batch into a single transaction',
            ],
            'solution' => "The mapper owns all SQL; Note knows nothing about storage. The identity map keys by id so repeated finds share instances.\nCollect dirty objects and flush() them inside one transaction — either every write lands or none does.",
            'tasks' => [
                ['brief' => 'Model Note (id, title, body, version) with no database code.', 'concepts' => ['class', 'encapsulation'], 'hint' => 'Pure domain object; a dirty flag can be internal state.'],
                ['brief' => 'Implement hydrate/persist in NoteMapper over PDO.', 'concepts' => ['data_mapper', 'pdo'], 'hint' => 'fetchAll → new Note(...) per row; prepared statements only.'],
                ['brief' => 'Add an identity map keyed by id.', 'concepts' => ['identity_map'], 'hint' => 'private array $loaded = []; find() returns the cached instance when present.'],
                ['brief' => 'Batch flush() inside beginTransaction/commit with rollback on error.', 'concepts' => ['unit_of_work', 'transactions'], 'hint' => 'try { begin; writes; commit; } catch (Throwable) { rollBack; throw; }.'],
            ],
        ],
        [
            'slug' => 'import-pipeline',
            'level' => 'advanced',
            'stage' => 7,
            'ord' => 11,
            'title' => 'Import Pipeline',
            'brief' => "A CSV import pipeline built from Command objects: validate → transform → load, each step composable, failures reported not swallowed.\nBehavioral patterns with honest error handling.",
            'requirements' => [
                'Each step is an object with a name and run(context)',
                'Pipeline stops on error or collects errors — chosen explicitly',
                'Failures carry file and row information',
                'Every step unit-testable in isolation',
            ],
            'solution' => "A Pipeline holds a list of Command objects; run() threads one mutable context through them.\nErrors are value objects collected in the context — the pipeline never echoes and never hides a failure.",
            'tasks' => [
                ['brief' => 'Define the Command interface and a Pipeline runner.', 'concepts' => ['behavioral', 'interface'], 'hint' => 'interface Step { public function name(): string; public function run(Context $c): void; }.'],
                ['brief' => 'Implement validate, transform and load steps.', 'concepts' => ['strategy'], 'hint' => 'Each step mutates only the context it needs; no cross-step globals.'],
                ['brief' => 'Collect per-row errors with file/line context.', 'concepts' => ['exception', 'error_handling'], 'hint' => 'catch per row, push an Error value object, continue or rethrow by policy.'],
                ['brief' => 'Write one test per step plus one full-pipeline test.', 'concepts' => ['phpunit', 'assertions'], 'hint' => 'Feed a tiny in-memory CSV; assert on the context result.'],
            ],
        ],
        [
            'slug' => 'schema-reflector',
            'level' => 'advanced',
            'stage' => 3,
            'ord' => 12,
            'title' => 'Schema Reflector',
            'brief' => "Introspect your own classes with Reflection and attributes to emit a schema document (array or JSON).\nModern PHP tooling: attributes as metadata, reflection as the reader.",
            'requirements' => [
                'Attributes carry field metadata (name, required, format)',
                'Reflection reads classes, properties and constructor params',
                'Output: array or JSON schema of the whole class',
                'Works on promoted properties',
            ],
            'solution' => "Attributes are the single source of truth; nothing duplicates metadata in docblocks.\nThe reflector walks ReflectionClass → properties → attributes and folds them into one array — the same shape a validator would consume.",
            'tasks' => [
                ['brief' => 'Create #[Field] and #[Required] attributes.', 'concepts' => ['attributes'], 'hint' => '#[Attribute(Attribute::TARGET_PROPERTY)] with constructor promotion.'],
                ['brief' => 'Build Reflector::schemaFor(string $class): array.', 'concepts' => ['reflection'], 'hint' => 'new ReflectionClass($class), getProperties(), getAttributes(Field::class).'],
                ['brief' => 'Handle constructor-promoted properties correctly.', 'concepts' => ['constructor_promotion', 'reflection'], 'hint' => 'Promoted params also appear as properties — check isPromoted() on the parameter.'],
                ['brief' => 'Emit JSON with json_encode and pretty print.', 'concepts' => ['cli_tools'], 'hint' => 'JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR.'],
            ],
        ],

        // --- Capstone ---
        [
            'slug' => 'issue-tracker',
            'level' => 'capstone',
            'stage' => 11,
            'ord' => 13,
            'title' => 'Issue Tracker (capstone)',
            'brief' => "A small issue tracker that brings the whole book together: domain model, persistence, strategies, tests and a front controller.\nEverything you practised, one codebase.",
            'requirements' => [
                'Domain: Issue, User, Project with guarded invariants',
                'Persistence behind a mapper with a transaction boundary',
                'Pluggable notifications via Strategy',
                'Test suite green with PHPUnit/Pest',
                'Front controller routing with typed responses',
            ],
            'solution' => "Slice it vertically: get one flow (open an issue) working end to end through routing, domain, mapper and test before adding the next.\nKeep the domain pure (no framework imports), push framework and SQL to the edges, and let the tests document each invariant as you add it.",
            'tasks' => [
                ['brief' => 'Model Issue with states (open → in_progress → closed) as guarded transitions.', 'concepts' => ['class', 'encapsulation', 'capstone'], 'hint' => 'One transition method per edge; invalid transitions throw DomainException.'],
                ['brief' => 'Persist issues through a mapper inside a transaction.', 'concepts' => ['data_mapper', 'transactions'], 'hint' => 'Reuse the data-mapper-notes shape; add optimistic versioning if you like.'],
                ['brief' => 'Plug in notifications through a Strategy chosen from config.', 'concepts' => ['strategy', 'di'], 'hint' => 'Interface Notifications, implementations Email/Log, factory wires it.'],
                ['brief' => 'Route GET/POST through the front controller.', 'concepts' => ['request_response', 'namespace'], 'hint' => 'Reuse the mini-router matcher; keep handlers thin.'],
                ['brief' => 'Write tests for every invariant and one HTTP-level test.', 'concepts' => ['phpunit', 'tdd'], 'hint' => 'Fail first: illegal transition, double-close, mapper round-trip.'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::FOUNDATION_PROJECTS as $project) {
            $this->seedProject($project);
        }

        foreach (self::ADVANCED_PROJECTS as $project) {
            $this->seedProject($project);
        }
    }

    /**
     * @param  array{slug: string, level: string, stage: int, ord: int, title: string, brief: string, requirements: list<string>, solution: string, tasks: list<array{brief: string, concepts: list<string>, hint: string}>}  $project
     */
    private function seedProject(array $project): void
    {
        $stageId = Stage::query()->where('number', $project['stage'])->value('id');

        $model = Project::updateOrCreate(
            ['slug' => $project['slug']],
            [
                'stage_id' => $stageId,
                'level' => ProjectLevel::from($project['level']),
                'title' => $project['title'],
                'brief' => $project['brief'],
                'requirements' => $project['requirements'],
                'solution_ref' => $project['solution'],
                'ord' => $project['ord'],
                'source' => ProvenanceSource::Ai,
                'status' => ContentStatus::Published,
                'ai_model' => 'gpt-4o',
                'ai_generated_at' => now(),
                'ai_prompt_version' => 'phase9-seed',
            ],
        );

        foreach ($project['tasks'] as $index => $task) {
            $model->tasks()->updateOrCreate(
                ['ord' => $index + 1],
                [
                    'brief' => $task['brief'],
                    'concept_ids' => $task['concepts'],
                    'solution_hint' => $task['hint'],
                ],
            );
        }
    }
}
