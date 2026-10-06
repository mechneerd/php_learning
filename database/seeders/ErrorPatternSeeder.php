<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\ErrorPattern;
use Illuminate\Database\Seeder;

/**
 * Ten error patterns across all six categories (docs/06, docs/04 FR-30).
 * Each pattern feeds the six-step learner flow:
 * what happened → why → identify → fix → prevent → practice.
 * Deterministic and idempotent (updateOrCreate on slug).
 */
class ErrorPatternSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string, name: string, category: string, symptom: string, cause: string,
     *     identify: list<string>, fix: list<string>, prevent: list<string>, practice: string|null
     * }>
     */
    private const PATTERNS = [
        [
            'slug' => 'unexpected-token',
            'name' => 'Parse error: unexpected token',
            'category' => 'parse',
            'symptom' => 'PHP Parse error: syntax error, unexpected token "}" in /app/report.php on line 14. Nothing on the page runs — the file never compiled.',
            'cause' => 'The parser reached a token it could not accept where an expression or statement was expected. Usual culprits: a missing ; before the line, an unclosed { ( or quote above it, or a stray character such as a hidden non-breaking arrow from a doc example.',
            'identify' => [
                'Read the reported line — and the line above it: the error is often where the parser STOPPED, not where the mistake is.',
                'Run php -l report.php from the terminal to get the error without running the app.',
                'Count braces and parentheses above the line: an unclosed { earlier shifts everything after it.',
                'Check for an unclosed quote or a copy-pasted curly quote (’ vs \').',
            ],
            'fix' => [
                'Fix the structure at the reported location: add the missing ;, ) or }.',
                'If the line looks correct, walk upwards until you find the unclosed block and close it there.',
                'Re-run php -l after each edit until it reports no syntax errors, then test the behaviour.',
            ],
            'prevent' => [
                'Run php -l (or your editor\'s PHP check) in a pre-commit hook.',
                'Enable real-time diagnostics in the editor so the squiggle appears as you type.',
                'Keep formatting consistent — a formatter (php-cs-fixer / Pint) makes stray characters visible in diffs.',
            ],
            'practice' => 'Practice: open Practice → Fix the broken snippets and repair three parse errors blind.',
        ],
        [
            'slug' => 'argument-type-mismatch',
            'name' => 'TypeError: argument type mismatch',
            'category' => 'type',
            'symptom' => 'PHP TypeError: App\\Pricing::total(): Argument #1 ($cents) must be of type int, string given, called in /app/order.php on line 22.',
            'cause' => 'A declared parameter type was violated. PHP 8 does not silently coerce when the value cannot safely convert (string "12.50" to int, or any object/array where a scalar is declared) — it throws a TypeError and aborts that call.',
            'identify' => [
                'Read the argument name in the message: it tells you exactly which parameter received the bad value.',
                'Trace the call site (file + line in the stack trace) and inspect what is being passed.',
                'var_dump() or a breakpoint at the caller: is it null, a numeric string, or the wrong class?',
                'Check whether the value passed validation upstream — often the real bug is a missing cast or check before the call.',
            ],
            'fix' => [
                'Convert at the boundary deliberately: (int) $raw or intval() with an explicit range check.',
                'If the caller is wrong, fix the caller — do not widen the callee\'s types to hide the bug.',
                'If null is legitimate, declare it: ?int and handle null explicitly.',
            ],
            'prevent' => [
                'Declare parameter and return types everywhere so mistakes fail fast at the call site.',
                'Validate and normalise input once, at the edge (request parsing, CLI argv), not inside every function.',
                'With declare(strict_types=1), coercions stop being silent — pair it with explicit casts.',
            ],
            'practice' => 'Practice: fix the TypeError in Practice → Write (mixed input to a typed function).',
        ],
        [
            'slug' => 'undefined-variable',
            'name' => 'Warning: Undefined variable',
            'category' => 'undefined',
            'symptom' => 'Warning: Undefined variable $total in /app/cart.php on line 8, and the total prints as empty (0 when concatenated into maths).',
            'cause' => 'The variable was read before any assignment in that scope. In PHP 8 an undefined variable is a Warning (not fatal): it evaluates to null, which silently corrupts arithmetic and conditions instead of stopping the request.',
            'identify' => [
                'Check the reported line first — but also check the spelling against the assignment ($total vs $totals).',
                'Confirm the assignment actually runs on every path: a variable set only inside an if branch is undefined on the else path.',
                'Look for scope mistakes: an include does not share local variables the way you might expect.',
                'Turn warnings into visibility early: set_error_handler() in dev that throws on E_WARNING.',
            ],
            'fix' => [
                'Assign a default before use: $total = 0; or $total = $fallback ?? 0;.',
                'Fix the typo or move the assignment so it dominates every read (all paths).',
                'If the value is optional, use isset()/?? explicitly so the default is intentional.',
            ],
            'prevent' => [
                'Initialise variables where you declare them, especially accumulators.',
                'Enable E_ALL in development so warnings surface immediately.',
                'Static analysis (PHPStan) flags undefined variables without running the code.',
            ],
            'practice' => 'Practice: run the cart snippet with E_ALL and eliminate every warning.',
        ],
        [
            'slug' => 'undefined-array-key',
            'name' => 'Warning: Undefined array key',
            'category' => 'undefined',
            'symptom' => 'Warning: Undefined array key "email" in /app/profile.php on line 15, and downstream code crashes trying to use null as a string.',
            'cause' => 'The key does not exist in that array (wrong name, data missing, or the structure differs from what you assumed). The read returns null — the warning is only the messenger; the real damage is null flowing onward.',
            'identify' => [
                'Print the array (array_keys($row)) at the failing line to see what keys really exist.',
                'Verify the data source: a CSV/JSON row may genuinely lack the column for some records.',
                'Check case and separators: "user_id" vs "userId" vs "user-id".',
                'Ask where null goes next — the second error (null as string/array) often hides the origin.',
            ],
            'fix' => [
                'Use null coalescing with a real default: $email = $row[\'email\'] ?? \'\';',
                'Or guard with array_key_exists()/isset() when absence must be handled differently from empty.',
                'If the key must exist, fail fast: if (! isset($row[\'email\'])) { throw new RuntimeException(...); }',
            ],
            'prevent' => [
                'Normalise external data into a typed shape (DTO or validated array) right after parsing.',
                '?? with an explicit default at every optional-key read.',
                'PHPStan learns the array shape from assertions and flags missing keys statically.',
            ],
            'practice' => 'Practice: harden the profile reader against rows with missing keys.',
        ],
        [
            'slug' => 'call-to-undefined-method',
            'name' => 'Error: Call to undefined method',
            'category' => 'method',
            'symptom' => 'Error: Call to undefined method App\\Invoice::calcTotal() in /app/billing.php on line 19.',
            'cause' => 'The method does not exist on the object\'s class (typo, wrong object, or a method that lives on a sibling class or trait that was never used). Method names are case-insensitive, so it is not capitalisation — it is a genuinely missing symbol.',
            'identify' => [
                'Read the class name in the message: are you sure that is the object you hold? (var_dump/get_class).',
                'Search the class (and its parents and traits) for the method name.',
                'Check for a typo or an outdated object created before a refactor.',
                'If the object comes from a collection, verify one element — a mixed-type array often sneaks in a different class.',
            ],
            'fix' => [
                'Call the right method name, or add the missing method where the behaviour belongs.',
                'If two sibling classes share behaviour, extract an interface or trait instead of copy-pasting.',
                'Fix the caller if it received the wrong object type.',
            ],
            'prevent' => [
                'Type-hint parameters and properties (Invoice $invoice) so mismatches fail earlier and clearer.',
                'Interfaces document what callers may rely on; implement them for related classes.',
                'Static analysis catches undefined methods without executing anything.',
            ],
            'practice' => 'Practice: refactor the billing code so Invoice and Quote share a Payable interface.',
        ],
        [
            'slug' => 'property-on-null',
            'name' => 'Attempt to read property on null',
            'category' => 'method',
            'symptom' => 'Warning: Attempt to read property "name" on null in /app/header.php on line 5 — the page renders without the name.',
            'cause' => 'A chain step returned null (a find() that found nothing, a missing array element, an uninitialised relation) and the code immediately dereferenced it. The read evaluates to null and execution continues — degraded output, not a crash.',
            'identify' => [
                'Find the first -> in the reported chain that could be null (usually a lookup or relation).',
                'Ask why it returned null: record not found? not loaded? wrong id?',
                'Log or dump the whole chain step by step rather than only the last variable.',
                'Check whether null is a legitimate state (user logged out, empty relation) or a bug upstream.',
            ],
            'fix' => [
                'Guard explicitly: if ($user === null) { /* fallback */ }.',
                'Short-circuit safely: $name = $user?->profile?->name ?? \'Guest\';',
                'If null should be impossible, assert it and throw — silence hides the real bug.',
            ],
            'prevent' => [
                'Use ?-> for chains where any step may legitimately be null.',
                'Give every lookup an explicit fallback or failure path at the call site.',
                'PHPStan with a strict level reports every unchecked null dereference.',
            ],
            'practice' => 'Practice: rewrite the header with null-safe operators and a Guest fallback.',
        ],
        [
            'slug' => 'memory-exhausted',
            'name' => 'Allowed memory size exhausted',
            'category' => 'fatal',
            'symptom' => 'Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in /app/import.php on line 87 — the script dies immediately.',
            'cause' => 'The process exceeded memory_limit. Classic causes: loading an entire CSV/JSON file into one array, growing a result set in a loop (accumulating every row), or a reference cycle that never gets collected.',
            'identify' => [
                'Note how much it tried to allocate at the end — small repeated allocations mean an ever-growing structure.',
                'Find the loop before the crash line: is every iteration keeping the previous ones alive?',
                'Check for file_get_contents() on huge files and Model::all()-style loads.',
                'Measure: memory_get_peak_usage(true) printed every N rows shows the growth curve.',
            ],
            'fix' => [
                'Stream instead of slurp: fgets()/fgetcsv() row by row, or generators (yield) for large sets.',
                'Process in chunks and free references you no longer need (unset).',
                'As a last resort raise memory_limit — but only after proving the data size justifies it.',
            ],
            'prevent' => [
                'Design imports and reports as generators or batches from day one.',
                'Set a deliberately small memory_limit in tests so growth bugs fail CI, not production.',
                'Prefer streaming APIs (SplFileObject) over file_get_contents + explode for files you do not control.',
            ],
            'practice' => 'Practice: convert the slurping importer into a generator and import 100k rows under 32MB.',
        ],
        [
            'slug' => 'class-not-found',
            'name' => 'Class not found (autoload miss)',
            'category' => 'fatal',
            'symptom' => 'Error: Class "App\\Pdf\\Reader" not found in /app/pipeline.php on line 12 — fatal at the first new or type hint mentioning it.',
            'cause' => 'Autoloading never found the file: a wrong or missing namespace in the class file, a typo in the class name, the class living outside PSR-4 paths, or the autoloader simply not being required.',
            'identify' => [
                'Compare the FQCN in the error with the namespace line at the top of the class file — case included.',
                'Check composer.json autoload.psr-4 maps App\\ to app/ and that the path matches the namespace.',
                'Confirm vendor/autoload.php is required in the entry script.',
                'Run composer dump-autoload after moving files; stale maps cause phantom misses.',
            ],
            'fix' => [
                'Correct the namespace/path so PSR-4 resolves: App\\Pdf\\Reader must live in app/Pdf/Reader.php.',
                'Or register the class in autoload files/classmap if it genuinely cannot follow PSR-4.',
                'Re-run composer dump-autoload (-o in production).',
            ],
            'prevent' => [
                'Let the IDE create files inside the right namespace and never hand-edit paths casually.',
                'composer dump-autoload in CI, plus a smoke test that new-s every public class.',
                'Keep classes inside the PSR-4 roots instead of scattering helper files.',
            ],
            'practice' => 'Practice: repair the broken namespace in the sample composer project.',
        ],
        [
            'slug' => 'uncaught-exception',
            'name' => 'Uncaught exception (fatal error)',
            'category' => 'exception',
            'symptom' => 'PHP Fatal error: Uncaught RuntimeException: connection refused in /app/queue/worker.php:42 Stack trace: #0 ... thrown in /app/queue/worker.php on line 42 — the script stops mid-flight.',
            'cause' => 'A throwable left every catch block on the call stack — either nobody catches it (missing try/catch around a risky call) or a catch rethrows without handling. Once it reaches the top, PHP treats it as a fatal error.',
            'identify' => [
                'Read the stack trace from top to bottom: frame 0 is where it was thrown.',
                'Check the exception class and message — they usually name the failing subsystem (db, file, network).',
                'Ask which frame SHOULD have handled it: often a repository throws and only the controller knows the user-facing recovery.',
                'For intermittent failures, log the previous exception ($e->getPrevious()) — the root cause is often chained.',
            ],
            'fix' => [
                'Catch at the boundary that can recover: HTTP layer → response; CLI worker → retry or exit code.',
                'Preserve context when wrapping: throw new \\RuntimeException(\'import failed\', 0, $e).',
                'Never catch and ignore — an empty catch turns a loud bug into silent corruption.',
            ],
            'prevent' => [
                'Define an exception policy: who throws, who catches, what wraps what (book Ch 4).',
                'Use finally for cleanup that must run whether or not an exception fired.',
                'In Laravel, report() renders the log and render() shapes the HTTP response — do not mix the two.',
            ],
            'practice' => 'Practice: wrap the flaky fetch in a typed exception hierarchy and handle it at the CLI boundary.',
        ],
        [
            'slug' => 'dynamic-property-deprecated',
            'name' => 'Deprecated: Creation of dynamic property',
            'category' => 'runtime',
            'symptom' => 'Deprecated: Creation of dynamic property App\\Point::$x is deprecated in /app/geometry.php on line 9. (PHP 8.2+; becomes an Error in a future version.)',
            'cause' => 'The class assigns $this->x without declaring the property (and without #[AllowDynamicProperties]). PHP deprecates implicit properties so that undeclared state — often a typo or a misplaced field — is caught early.',
            'identify' => [
                'Read the property name in the message and compare it with the class\'s declared properties.',
                'Search the codebase for other assignments to undeclared names on that class.',
                'Check for magic __set()-based patterns — they may be intentional, but a plain assignment is not.',
                'Look for serialization or hydration code that sets fields from external data.',
            ],
            'fix' => [
                'Declare the property (typed where possible): public float $x;.',
                'Or initialise it in the constructor together with the others.',
                'If the class is a data bag by design, add #[AllowDynamicProperties] deliberately — not as a blanket fix.',
            ],
            'prevent' => [
                'Declare every property up front; typed properties reject wrong values at assignment.',
                'Turn deprecations into exceptions in development (error_reporting = E_ALL).',
                'Run your suite on the newest PHP version in CI so deprecations surface before the upgrade.',
            ],
            'practice' => 'Practice: migrate the dynamic-property bag to declared, typed properties.',
        ],
    ];

    public function run(): void
    {
        foreach (self::PATTERNS as $pattern) {
            ErrorPattern::updateOrCreate(
                ['slug' => $pattern['slug']],
                [
                    'name' => $pattern['name'],
                    'category' => $pattern['category'],
                    'symptom' => $pattern['symptom'],
                    'cause' => $pattern['cause'],
                    'identify_steps' => $pattern['identify'],
                    'fix_steps' => $pattern['fix'],
                    'prevent_steps' => $pattern['prevent'],
                    'practice_ref' => $pattern['practice'],
                    'source' => ProvenanceSource::Ai,
                    'status' => ContentStatus::Published,
                    'ai_model' => 'gpt-4o',
                    'ai_generated_at' => now(),
                    'ai_prompt_version' => 'phase9-seed',
                ],
            );
        }
    }
}
