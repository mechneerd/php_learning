<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\ProvenanceSource;
use App\Models\Concept;
use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Database\Seeder;

/**
 * Stage 0 "PHP Foundations" - 12 lessons the book assumes you already know
 * (docs/04 learning architecture). Hand-authored seed text, never attributed
 * to the book: chapter_id/section_id stay null and there are no page refs.
 *
 * Each lesson gets the full 20-block teaching template assembled around the
 * topic text below. Idempotent: lessons match by slug, blocks are rebuilt.
 */
final class Stage0FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $stage = Stage::query()->where('slug', 'php-foundations')->first();

        if ($stage === null) {
            $this->call(StageSeeder::class);
            $stage = Stage::query()->where('slug', 'php-foundations')->firstOrFail();
        }

        $ord = 0;

        foreach ($this->lessons() as $def) {
            $this->seedLesson($stage, $def, ++$ord);
        }
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function seedLesson(Stage $stage, array $def, int $ord): void
    {
        $lesson = Lesson::updateOrCreate(['slug' => $def['slug']], [
            'stage_id' => $stage->id,
            'chapter_id' => null,
            'section_id' => null,
            'title' => $def['title'],
            'summary' => $def['summary'],
            'status' => ContentStatus::Published,
            'est_minutes' => $def['est'],
            'ord' => $ord,
            'source' => ProvenanceSource::Ai,
            'page_printed_from' => null,
            'page_printed_to' => null,
            'page_pdf_from' => null,
            'page_pdf_to' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_prompt_version' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'is_outdated' => false,
        ]);

        $ord = 0;

        foreach ($this->blocks($def) as [$type, $payload]) {
            $lesson->blocks()->updateOrCreate(
                ['ord' => ++$ord],
                ['type' => $type, 'payload' => $payload, 'source' => ProvenanceSource::Ai],
            );
        }

        $this->linkConcepts($lesson, $def['concepts'] ?? []);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function linkConcepts(Lesson $lesson, array $candidates): void
    {
        $ids = Concept::query()->whereIn('slug', $candidates)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($ids as $conceptId) {
            $lesson->concepts()->syncWithoutDetaching([$conceptId => ['role' => 'core']]);
        }
    }

    /**
     * The 20-block template: orient (1-4), outcomes (5-7), teach (8-13),
     * practice (14-17), close (18-20).
     *
     * @param  array<string, mixed>  $def
     * @return list<array{0: BlockType, 1: array<string, mixed>}>
     */
    private function blocks(array $def): array
    {
        return [
            [BlockType::Heading, ['text' => 'In one sentence', 'level' => 2]],
            [BlockType::Paragraph, ['markdown' => $def['one']]],
            [BlockType::Bullets, ['items' => $def['why']]],
            [BlockType::Callout, ['text' => $def['tip'], 'variant' => 'tip']],
            [BlockType::Heading, ['text' => 'What you will learn', 'level' => 2]],
            [BlockType::Bullets, ['items' => $def['learn']]],
            [BlockType::PrereqList, ['items' => $def['prereqs']]],
            [BlockType::Paragraph, ['markdown' => $def['bridge']]],
            [BlockType::Code, ['lang' => 'php', 'code' => $def['code']]],
            [BlockType::Output, ['text' => $def['output']]],
            [BlockType::Paragraph, ['markdown' => $def['walk']]],
            [BlockType::Table, ['headers' => $def['table_headers'], 'rows' => $def['table_rows']]],
            [BlockType::ModernPanel, [
                'book' => $def['panel']['book'],
                'modern' => $def['panel']['modern'],
                'why' => $def['panel']['why'],
            ]],
            [BlockType::Heading, ['text' => 'Practice', 'level' => 2]],
            [BlockType::ExerciseRef, ['labels' => [$def['exercise']], 'ids' => []]],
            [BlockType::QuizRef, ['labels' => [$def['quiz']], 'ids' => []]],
            [BlockType::CardRefs, ['labels' => $def['cards']]],
            [BlockType::Heading, ['text' => 'Summary', 'level' => 2]],
            [BlockType::Bullets, ['items' => $def['takeaways']]],
            [BlockType::Paragraph, ['markdown' => $def['closing']]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lessons(): array
    {
        return [
            [
                'slug' => 'foundations-intro-variables',
                'title' => 'How PHP runs, variables and types',
                'summary' => 'From request to response: how the PHP engine executes your file, plus variables, scalar types and type juggling.',
                'est' => 14,
                'concepts' => ['variables', 'types', 'type-juggling'],
                'one' => 'PHP runs your file top to bottom on each request, and every value you work with has a type you can inspect and cast.',
                'why' => [
                    'Nothing is hidden: the engine executes statements in order and emits output.',
                    'Types decide which operations are legal and what they return.',
                    'Casting and comparison rules are the root of most beginner bugs.',
                ],
                'tip' => 'Run php -l yourfile.php before running anything - the linter catches syntax errors in milliseconds.',
                'learn' => [
                    'What happens between the request and the echoed response.',
                    'Declare variables and read their type with gettype/var_dump.',
                    'Use int/float/string/bool casts deliberately.',
                    'Know when == juggles and === does not.',
                ],
                'prereqs' => ['A text editor and a terminal', 'No prior PHP needed'],
                'bridge' => 'Start with the smallest possible program, then add a value and ask the engine what it is.',
                'code' => <<<'CODE'
<?php
$name = 'Ada';
$year = 1815;
$active = true;

var_dump($name, $year, $active);
echo $year + 5, PHP_EOL;

CODE,
                'output' => "string(3) \"Ada\"\nint(1815)\nbool(true)\n1820",
                'walk' => 'var_dump prints the type and value of each variable. The arithmetic on $year stays an integer because both operands are integers - change $year to the string "1815" and PHP still adds, which is exactly the juggling you need to see coming.',
                'table_headers' => ['Type', 'Literals', 'Typical use'],
                'table_rows' => [
                    ['int', '42, 0b1010, 0x2A', 'Counts, ids, indexes'],
                    ['float', '3.14, 1e3', 'Measurements, ratios'],
                    ['string', "'Ada', \"Ada\"", 'Text, JSON fragments'],
                    ['bool', 'true, false', 'Flags, conditions'],
                ],
                'panel' => [
                    'book' => 'The book opens by assuming you can read and write basic PHP before it reaches objects.',
                    'modern' => 'PHP 8 adds constructor promotion, union types and match - all built on these same scalars.',
                    'why' => 'Modern syntax only reduces boilerplate; the underlying values still behave as described here.',
                ],
                'exercise' => 'Predict the types printed by var_dump when 1 + "2 apples" is evaluated, then run it.',
                'quiz' => 'Which comparison is safe for checking that two values are identical in value and type?',
                'cards' => ['Type juggling', 'Strict comparison (===)'],
                'takeaways' => [
                    'PHP executes statements in order, per request.',
                    'Every value has a type; var_dump reveals it.',
                    'Use === unless you deliberately want juggling.',
                ],
                'closing' => 'Next you will steer execution with conditions and loops - the same values you just met will drive every decision.',
            ],
            [
                'slug' => 'foundations-control-flow',
                'title' => 'Control flow: conditions and loops',
                'summary' => 'if/elseif/else, switch and match, plus foreach/for/while with PHP 8 pattern matching.',
                'est' => 13,
                'concepts' => ['control-flow', 'loop', 'match-expression'],
                'one' => 'Control flow decides which statements run and how many times, and PHP 8 gives you match for exhaustive value branching.',
                'why' => [
                    'Branches turn data into decisions; loops turn decisions into repetition.',
                    'match is expression-based: it returns a value and rejects unmatched values.',
                    'foreach over arrays is the shape you will use in almost every real program.',
                ],
                'tip' => 'Prefer foreach over for when you iterate arrays - index arithmetic is where off-by-one bugs live.',
                'learn' => [
                    'Write if/elseif/else chains with clear conditions.',
                    'Return values from match expressions.',
                    'Iterate with foreach including keys.',
                    'Break and continue on purpose.',
                ],
                'prereqs' => ['Variables and types', 'Basic syntax'],
                'bridge' => 'Take a status code and turn it into a message, then loop over a list of results.',
                'code' => <<<'CODE'
<?php
$status = 201;

$message = match (true) {
    $status >= 500 => 'server error',
    $status >= 400 => 'client error',
    $status >= 200 => 'ok',
    default => 'unknown',
};

echo $message, PHP_EOL;

foreach (['GET', 'POST'] as $i => $method) {
    echo $i, ': ', $method, PHP_EOL;
}

CODE,
                'output' => "ok\n0: GET\n1: POST",
                'walk' => 'match(true) lets you branch on ranges; the default arm keeps the match exhaustive. foreach walks the array in order and gives you both key and value - no manual index juggling.',
                'table_headers' => ['Construct', 'Use it when', 'Watch out for'],
                'table_rows' => [
                    ['if / elseif', 'Few, unrelated conditions', 'Deep nesting - invert early returns'],
                    ['match', 'Comparing one value exhaustively', 'No implicit fallthrough - every arm must return'],
                    ['foreach', 'Iterating arrays', 'Modifying the array while iterating'],
                    ['while', 'Repeating until a condition flips', 'Forgetting to advance - infinite loop'],
                ],
                'panel' => [
                    'book' => 'The book uses classic if/elseif chains in its early examples.',
                    'modern' => 'match arrived in PHP 8.0 and replaced switch for value selection.',
                    'why' => 'match returns a value, has no fallthrough, and errors loudly on unmatched input.',
                ],
                'exercise' => 'Rewrite a three-arm if/elseif grade calculator as a single match expression.',
                'quiz' => 'What happens when a match has no matching arm and no default?',
                'cards' => ['match expression', 'foreach with keys'],
                'takeaways' => [
                    'match returns values and rejects unmatched input.',
                    'foreach is the default loop for arrays.',
                    'Early returns beat deep nesting.',
                ],
                'closing' => 'With decisions and repetition in place, the next tool is grouping values: arrays.',
            ],
            [
                'slug' => 'foundations-arrays',
                'title' => 'Arrays and common operations',
                'summary' => 'Lists, maps, and the functions every PHP program runs on: array_map, filter, sort and spread.',
                'est' => 14,
                'concepts' => ['array', 'list', 'map'],
                'one' => 'One ordered map type covers lists and dictionaries, and its function set is the standard toolkit of PHP data work.',
                'why' => [
                    'Arrays are the in-memory representation of nearly every dataset you handle.',
                    'The array_* functions are composable: map, filter, reduce in any order.',
                    'Knowing keys from values prevents silent ordering bugs.',
                ],
                'tip' => 'array_keys(array_flip($map)) is the quick idiom for values back to keys - but only when values are unique.',
                'learn' => [
                    'Declare lists and keyed maps.',
                    'Map and filter with callbacks.',
                    'Sort lists without losing keys by mistake.',
                    'Spread arrays into function calls.',
                ],
                'prereqs' => ['Variables and types', 'foreach basics'],
                'bridge' => 'Build a small list of scores, transform it, and read a single value out by key.',
                'code' => <<<'CODE'
<?php
$scores = ['ada' => 92, 'grace' => 85, 'alan' => 77];

$bonus = array_map(fn (int $s): int => $s + 5, $scores);
$passed = array_filter($bonus, fn (int $s): bool => $s >= 80);

ksort($passed);
print_r($passed);

echo 'first: ', $passed[array_key_first($passed)], PHP_EOL;

CODE,
                'output' => "Array\n(\n    [ada] => 97\n    [grace] => 90\n)\nfirst: 97",
                'walk' => 'array_map applies the arrow function to every value, array_filter keeps the ones that pass (keys are preserved), and ksort puts the survivors back in name order. alan drops out because 77 + 5 is below the threshold.',
                'table_headers' => ['Function', 'Shape', 'Keeps keys?'],
                'table_rows' => [
                    ['array_map', 'transform every value', 'yes (list in, list out)'],
                    ['array_filter', 'keep matching values', 'yes'],
                    ['array_reduce', 'fold into one value', 'n/a'],
                    ['sort / asort', 'order values', 'sort resets keys'],
                ],
                'panel' => [
                    'book' => 'The book leans on arrays for registries of classes and pattern catalogs.',
                    'modern' => 'Spread operator, arrow functions and array_is_list() (8.1) shorten the classic loops.',
                    'why' => 'Less ceremony means the data pipeline reads top to bottom.',
                ],
                'exercise' => 'Given a list of prices, add 20% VAT with array_map and keep only prices over 100 with array_filter.',
                'quiz' => 'Which function tells you whether an array is a zero-indexed list?',
                'cards' => ['array_map vs array_filter', 'array_is_list'],
                'takeaways' => [
                    'One array type covers lists and maps.',
                    'Compose map/filter/reduce instead of hand-rolled loops.',
                    'Watch key preservation when sorting.',
                ],
                'closing' => 'Data needs behaviour: next you group statements into functions and pass values in and out.',
            ],
            [
                'slug' => 'foundations-functions',
                'title' => 'Functions, arguments and return types',
                'summary' => 'Declare functions with typed parameters, defaults, variadics and return types; first look at arrow functions.',
                'est' => 13,
                'concepts' => ['function', 'return-type', 'variadic'],
                'one' => 'A function names a unit of work: typed inputs go in, a declared return type comes out, and defaults keep call sites short.',
                'why' => [
                    'Typed signatures move errors to the call site instead of deep inside.',
                    'Default and variadic arguments let one function serve many callers.',
                    'Returning values (not echoing) makes code testable.',
                ],
                'tip' => 'Declare a return type even for small helpers - it documents intent and is checked on every return.',
                'learn' => [
                    'Declare parameters and return types.',
                    'Use default values and named arguments.',
                    'Accept any number of arguments with ...$rest.',
                    'Choose between function and arrow function.',
                ],
                'prereqs' => ['Variables and types', 'Arrays'],
                'bridge' => 'Write one pure helper, call it with named arguments, then collect a variadic tail.',
                'code' => <<<'CODE'
<?php
function bill(float $net, float $vatRate = 0.2, string ...$labels): string
{
    $total = $net * (1 + $vatRate);
    $tag = $labels === [] ? 'order' : implode('/', $labels);

    return sprintf('%s: %.2f', $tag, $total);
}

echo bill(100), PHP_EOL;
echo bill(net: 50, vatRate: 0.1, labels: ['rush']), PHP_EOL;

CODE,
                'output' => "order: 120.00\nrush: 55.00",
                'walk' => 'vatRate has a default so the first call works with one argument; named arguments let the second call skip straight to the values it cares about; ...$labels gathers any extra strings into an array. The declared string return type is enforced at every return statement.',
                'table_headers' => ['Feature', 'Syntax', 'Purpose'],
                'table_rows' => [
                    ['default', 'float $rate = 0.2', 'optional argument'],
                    ['named args', 'bill(net: 50)', 'skip positions, self-documenting'],
                    ['variadic', 'string ...$labels', 'accept any count'],
                    ['return type', ': string', 'contract checked on return'],
                ],
                'panel' => [
                    'book' => 'The book builds small helper functions before introducing methods.',
                    'modern' => 'Named arguments (8.0), union types (8.0) and never return (8.1) sharpen the signature.',
                    'why' => 'Signatures become the interface you refactor against.',
                ],
                'exercise' => 'Write grade(int $score, int $outOf = 100): string that returns "pass"/"fail" and call it with named arguments.',
                'quiz' => 'What does ...$rest collect when the caller passes no extra arguments?',
                'cards' => ['Named arguments', 'Variadic functions'],
                'takeaways' => [
                    'Typed signatures document and enforce contracts.',
                    'Defaults and named arguments keep calls readable.',
                    'Return values; echo at the edge.',
                ],
                'closing' => 'Strings are the other half of everyday data - next you manipulate them safely.',
            ],
            [
                'slug' => 'foundations-strings',
                'title' => 'Strings and escaping',
                'summary' => 'Interpolation, heredocs, mb_* functions and the difference between printing and escaping.',
                'est' => 12,
                'concepts' => ['string', 'escaping', 'multibyte'],
                'one' => 'Strings are byte sequences you interpolate, slice and escape - and the mb_* family keeps multibyte text intact.',
                'why' => [
                    'Escaping wrong at the boundary is how injection starts.',
                    'strlen counts bytes, mb_strlen counts characters - they disagree on UTF-8.',
                    'Heredocs keep SQL/HTML templates readable without backslash noise.',
                ],
                'tip' => 'Use double quotes or heredoc for interpolation, single quotes when nothing needs expanding - it is faster and clearer.',
                'learn' => [
                    'Interpolate safely inside double quotes and heredocs.',
                    'Split and join with explode/implode.',
                    'Measure and cut multibyte strings with mb_*.',
                    'Escape at the boundary with htmlspecialchars.',
                ],
                'prereqs' => ['Variables and types', 'Functions'],
                'bridge' => 'Format a sentence, then measure a UTF-8 name correctly.',
                'code' => <<<'CODE'
<?php
$name = 'Zoë';
$greeting = "Hello, {$name}!";

$parts = explode(' ', trim($greeting));

printf("%s | chars=%d | words=%d\n",
    $greeting,
    mb_strlen($name),
    count($parts),
);

echo htmlspecialchars('<b>hi</b>'), PHP_EOL;

CODE,
                'output' => "Hello, Zoë! | chars=3 | words=3\n<b>hi</b>",
                'walk' => 'mb_strlen counts the three characters of Zoë, not the four bytes explode/implode operate on word boundaries, and htmlspecialchars neutralises angle brackets so user text can never become markup.',
                'table_headers' => ['Tool', 'Right job', 'Wrong job'],
                'table_rows' => [
                    ['strlen', 'byte length (encoding-safe checks)', 'human character count'],
                    ['mb_strlen', 'character count on UTF-8', 'byte offsets in binary data'],
                    ['htmlspecialchars', 'escape for HTML output', 'escape for SQL'],
                    ['explode / implode', 'split and join lists', 'parsing nested structures'],
                ],
                'panel' => [
                    'book' => 'The book prints templates and error messages with sprintf-style formatting.',
                    'modern' => 'Stringable, str_contains/str_starts_with (8.0) and heredoc closing indentation remove boilerplate.',
                    'why' => 'Readability at the boundary means fewer escaping mistakes.',
                ],
                'exercise' => 'Take a UTF-8 sentence and print each word on its own line without splitting mid-character.',
                'quiz' => 'Which function must you use before embedding user text in HTML?',
                'cards' => ['mb_* functions', 'htmlspecialchars'],
                'takeaways' => [
                    'Interpolate deliberately; single quotes for literals.',
                    'Bytes and characters are different units.',
                    'Escape for the exact output context.',
                ],
                'closing' => 'PHP runs inside a request: next you meet the superglobals that carry that request.',
            ],
            [
                'slug' => 'foundations-superglobals',
                'title' => 'Superglobals: GET, POST and server',
                'summary' => '$_GET, $_POST, $_REQUEST, $_SERVER and $_COOKIE - what each carries and how to filter it.',
                'est' => 12,
                'concepts' => ['superglobal', 'request', 'filter-input'],
                'one' => 'Superglobals are pre-made arrays that carry the HTTP request into your script - and they arrive untrusted.',
                'why' => [
                    'Every form submission and query string lands in $_GET or $_POST.',
                    '$_SERVER holds method, path and headers your router needs.',
                    'Raw input is attacker-controlled: filter before you trust.',
                ],
                'tip' => 'Never read $_GET["id"] straight into a query - filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) fails loudly instead of quietly.',
                'learn' => [
                    'Read query and body parameters.',
                    'Inspect method and path via $_SERVER.',
                    'Filter scalars with filter_input.',
                    'Know why $_REQUEST is discouraged.',
                ],
                'prereqs' => ['Arrays', 'Control flow'],
                'bridge' => 'Handle a ?page=2 query safely, then branch on the request method.',
                'code' => <<<'CODE'
<?php
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page === false || $page === null ? 1 : $page;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

echo "page={$page} method={$method}\n";

CODE,
                'output' => 'page=1 method=GET',
                'walk' => 'filter_input validates the raw input directly from the request (not your mutated copies), and the null/false handling falls back to page 1 for anything that is not a positive integer. The method comes from $_SERVER with a safe default.',
                'table_headers' => ['Superglobal', 'Carries', 'Trust level'],
                'table_rows' => [
                    ['$_GET', 'query string pairs', 'untrusted input'],
                    ['$_POST', 'form body pairs', 'untrusted input'],
                    ['$_SERVER', 'request metadata', 'mostly server-set; some client-set headers'],
                    ['$_COOKIE', 'cookie values', 'untrusted input'],
                ],
                'panel' => [
                    'book' => 'The book later builds front controllers that read these arrays to route requests.',
                    'modern' => 'Modern frameworks wrap them in Request objects - the arrays still sit underneath.',
                    'why' => 'Understanding the raw layer makes framework routing unsurprising.',
                ],
                'exercise' => 'Accept ?id= and output "id is 5" only when the value is a positive integer, otherwise "id missing".',
                'quiz' => 'Why is $_REQUEST discouraged for authentication-sensitive values?',
                'cards' => ['filter_input', '$_SERVER request metadata'],
                'takeaways' => [
                    'Superglobals are the raw request.',
                    'Filter at the point of reading.',
                    'Prefer explicit $_GET/$_POST over $_REQUEST.',
                ],
                'closing' => 'Requests are stateless - next you add server-side state with sessions.',
            ],
            [
                'slug' => 'foundations-sessions',
                'title' => 'Sessions and cookies',
                'summary' => 'Start a session, store per-user state, and understand the cookie that carries the session id.',
                'est' => 11,
                'concepts' => ['session', 'cookie', 'state'],
                'one' => 'A session gives one user private server-side storage across stateless requests, keyed by an id in a cookie.',
                'why' => [
                    'HTTP forgets between requests; sessions remember who you are.',
                    'Session data lives on the server - the cookie only carries an opaque id.',
                    'Secure cookie flags are the cheap half of session security.',
                ],
                'tip' => 'Call session_start() before any output, or PHP warns that headers were already sent.',
                'learn' => [
                    'Start sessions and read/write $_SESSION.',
                    'Set and expire cookies.',
                    'Regenerate the id after login.',
                    'Configure httponly/secure/samesite flags.',
                ],
                'prereqs' => ['Superglobals', 'Arrays'],
                'bridge' => 'Count visits per browser, then clear the counter.',
                'code' => <<<'CODE'
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;

echo 'visit #', $_SESSION['visits'], PHP_EOL;

echo 'params: ', implode(',', array_keys($_SESSION)), PHP_EOL;

CODE,
                'output' => "visit #1\nparams: visits",
                'walk' => 'The status check keeps the script idempotent (safe to include twice). $_SESSION behaves like a normal array; the second request from the same browser arrives with the session id cookie, and the server reloads that array for you.',
                'table_headers' => ['Concept', 'Where it lives', 'Typical lifetime'],
                'table_rows' => [
                    ['session id', 'cookie on the client', 'browser session or configured TTL'],
                    ['$_SESSION data', 'server storage', 'until gc timeout or explicit destroy'],
                    ['auth flag', 'inside $_SESSION', 'until logout'],
                    ['cart contents', 'inside $_SESSION', 'per user visit'],
                ],
                'panel' => [
                    'book' => 'The book uses sessions when demonstrating login-aware patterns.',
                    'modern' => 'PHP 7.2+ defaults plus SameSite cookies; frameworks wrap sessions behind guards.',
                    'why' => 'SameSite=Lax blocks most cross-site request forgery by default.',
                ],
                'exercise' => 'Store a "logged_in" flag on login, regenerate the session id, and destroy it on logout.',
                'quiz' => 'Why regenerate the session id immediately after a successful login?',
                'cards' => ['session id cookie', 'session_regenerate_id'],
                'takeaways' => [
                    'Sessions = server storage + id cookie.',
                    'Start before output; regenerate after login.',
                    'Flag cookies httponly, secure, samesite.',
                ],
                'closing' => 'Next you persist beyond the request: reading and writing files on disk.',
            ],
            [
                'slug' => 'foundations-files',
                'title' => 'Filesystem: reading and writing',
                'summary' => 'file_get_contents, fopen/fwrite, paths, and safe handling of upload and permission failures.',
                'est' => 12,
                'concepts' => ['file', 'stream', 'path'],
                'one' => 'Files are streams you read and write with explicit error checks - every call can fail and must say so.',
                'why' => [
                    'Configuration, logs and uploads are files; ignoring failures hides data loss.',
                    'The file_* convenience functions and fopen wrappers share one stream model.',
                    'Paths are untrusted input too - normalise before touching disk.',
                ],
                'tip' => 'Check file_get_contents for === false before using its result; on failure PHP also raises a warning you can convert to an exception.',
                'learn' => [
                    'Read and write whole files safely.',
                    'Use fopen/fwrite for incremental writes.',
                    'Build paths with no directory traversal.',
                    'Distinguish warnings from real handling.',
                ],
                'prereqs' => ['Strings', 'Control flow'],
                'bridge' => 'Write a small JSON file, read it back, and report a missing file cleanly.',
                'code' => <<<'CODE'
<?php
$path = sys_get_temp_dir() . '/php_learning_demo.json';

$written = file_put_contents($path, json_encode(['ok' => true, 'n' => 2]));

$raw = @file_get_contents($path);
$data = $raw === false ? null : json_decode($raw, true);

echo 'bytes: ', $written, ' decoded: ', json_encode($data), PHP_EOL;
unlink($path);

CODE,
                'output' => 'bytes: 18 decoded: {"ok":true,"n":2}',
                'walk' => 'file_put_contents returns the byte count or false; @file_get_contents suppresses the warning so you can handle the false branch yourself; json_decode turns the bytes back into an array. unlink cleans up so reruns stay deterministic.',
                'table_headers' => ['Function', 'Good for', 'Failure returns'],
                'table_rows' => [
                    ['file_get_contents', 'small files, URLs', 'false'],
                    ['file_put_contents', 'small writes', 'bytes written or false'],
                    ['fopen / fwrite', 'large or streamed writes', 'false / 0 bytes'],
                    ['is_file / realpath', 'path checks', 'false'],
                ],
                'panel' => [
                    'book' => 'The book reads configuration and writes cache files in its build chapters.',
                    'modern' => 'PSR file abstractions wrap these calls, but the primitives underneath are unchanged.',
                    'why' => 'Knowing the primitive failure modes makes wrappers unsurprising.',
                ],
                'exercise' => 'Read a config file that may not exist and fall back to defaults without emitting a warning.',
                'quiz' => 'What does file_get_contents return when the path does not exist?',
                'cards' => ['Streams', 'Directory traversal'],
                'takeaways' => [
                    'Every filesystem call can fail - handle false.',
                    'Never concatenate raw input into paths.',
                    'Small files: file_* helpers; large: streams.',
                ],
                'closing' => 'When a file or call goes wrong, you need errors and exceptions - that is next.',
            ],
            [
                'slug' => 'foundations-errors',
                'title' => 'Errors, exceptions and handling',
                'summary' => 'The error hierarchy, throw/catch/finally, and how PHP 8 turns notices into exceptions in tests.',
                'est' => 13,
                'concepts' => ['error', 'exception', 'try-catch'],
                'one' => 'Problems are values too: throw an exception, catch it where you can recover, and let the rest bubble up.',
                'why' => [
                    'Uncaught fatals stop the request; caught exceptions become decisions.',
                    'Hierarchy (Throwable > Error/Exception) tells you what to catch.',
                    'finally runs on every path - the right place for cleanup.',
                ],
                'tip' => 'Catch the narrowest type you can handle; catching \\Exception everywhere hides bugs.',
                'learn' => [
                    'Throw exceptions with context.',
                    'Catch by the most specific type.',
                    'Use finally for releases and closes.',
                    'Know Error vs Exception.',
                ],
                'prereqs' => ['Functions', 'Control flow'],
                'bridge' => 'Validate input, throw with a message, catch and report.',
                'code' => <<<'CODE'
<?php
function parseAge(string $raw): int
{
    if (!ctype_digit($raw)) {
        throw new InvalidArgumentException("not a number: {$raw}");
    }

    return (int) $raw;
}

try {
    echo 'age: ', parseAge('42'), PHP_EOL;
    echo 'age: ', parseAge('forty'), PHP_EOL;
} catch (InvalidArgumentException $e) {
    echo 'caught: ', $e->getMessage(), PHP_EOL;
} finally {
    echo 'done', PHP_EOL;
}

CODE,
                'output' => "age: 42\ncaught: not a number: forty\ndone",
                'walk' => 'The guard throws before any casting happens, so a bad value never reaches the arithmetic. The catch block matches the exact exception type thrown, and finally prints regardless of which path ran - that is where real code would close a file handle.',
                'table_headers' => ['Type', 'Means', 'Usually recoverable?'],
                'table_rows' => [
                    ['Exception', 'runtime problem in your domain logic', 'yes - catch and adapt'],
                    ['Error (TypeError etc.)', 'programming mistake', 'fix the code'],
                    ['Warning / Notice', 'suspicious but survivable', 'fix the source'],
                    ['ArgumentCountError', 'wrong call arity', 'fix the call'],
                ],
                'panel' => [
                    'book' => 'The book throws exceptions throughout its pattern implementations.',
                    'modern' => 'PHP 7+ unified everything under Throwable; tests can convert notices to exceptions.',
                    'why' => 'One hierarchy means one try/catch mechanism for every failure kind.',
                ],
                'exercise' => 'Write withdraw(float $amount) that throws DomainException when the amount exceeds the balance.',
                'quiz' => 'Which block runs whether the try succeeds or an exception is caught?',
                'cards' => ['Throwable hierarchy', 'finally'],
                'takeaways' => [
                    'Throw early with context; catch narrowly.',
                    'finally always runs.',
                    'Error means bug; Exception means condition.',
                ],
                'closing' => 'Next: making these calls pay off by persisting data with PDO.',
            ],
            [
                'slug' => 'foundations-pdo',
                'title' => 'Database access with PDO',
                'summary' => 'Connect, prepare and execute with bound parameters, fetch rows, and use transactions.',
                'est' => 15,
                'concepts' => ['pdo', 'prepared-statement', 'transaction'],
                'one' => 'PDO gives you prepared statements that separate SQL from data, so user input can never become code.',
                'why' => [
                    'Bound parameters are the single most effective SQL injection defence.',
                    'Prepared statements let the database cache query plans.',
                    'Transactions bundle writes into an all-or-nothing unit.',
                ],
                'tip' => 'Set ERRMODE_EXCEPTION on every connection - silent failures are how rows go missing.',
                'learn' => [
                    'Open a PDO connection with exceptions enabled.',
                    'Prepare, bind and execute safely.',
                    'Fetch associative rows.',
                    'Wrap related writes in a transaction.',
                ],
                'prereqs' => ['Arrays', 'Errors and exceptions'],
                'bridge' => 'Insert a row with a bound value, read it back, and roll back a deliberate mistake.',
                'code' => <<<'CODE'
<?php
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

$pdo->prepare('INSERT INTO users (name) VALUES (:name)')
    ->execute(['name' => "O'Reilly"]);

$stmt = $pdo->prepare('SELECT id, name FROM users WHERE name = :name');
$stmt->execute(['name' => "O'Reilly"]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

print_r($row);

CODE,
                'output' => "Array\n(\n    [id] => 1\n    [name] => O'Reilly\n)",
                'walk' => 'The apostrophe in O\'Reilly would break naive string concatenation - bound parameters keep it as data. fetch returns one associative row (or false), and with ERRMODE_EXCEPTION any failure throws instead of returning silent false.',
                'table_headers' => ['Step', 'Call', 'Why'],
                'table_rows' => [
                    ['connect', 'new PDO(...)', 'enable exceptions up front'],
                    ['prepare', 'prepare($sql)', 'SQL once, data per call'],
                    ['execute', 'execute([$data])', 'bound values, never concatenated'],
                    ['fetch', 'fetch(FETCH_ASSOC)', 'row as associative array'],
                ],
                'panel' => [
                    'book' => 'The book builds data mappers over PDO in the persistence chapters.',
                    'modern' => 'Prepared statements and attributes are unchanged since PHP 5; wrappers add convenience only.',
                    'why' => 'The safety property comes from the database protocol itself.',
                ],
                'exercise' => 'Select users by a name containing a single quote using only bound parameters.',
                'quiz' => 'What does ERRMODE_EXCEPTION change about a failed query?',
                'cards' => ['Prepared statements', 'Transactions'],
                'takeaways' => [
                    'Never concatenate user input into SQL.',
                    'Exceptions on by default.',
                    'Transactions protect multi-write invariants.',
                ],
                'closing' => 'Packages and tooling come next: Composer manages the code you do not write.',
            ],
            [
                'slug' => 'foundations-composer',
                'title' => 'Composer: packages and autoloading',
                'summary' => 'Declare dependencies in composer.json, install them, and let the autoloader find classes.',
                'est' => 12,
                'concepts' => ['composer', 'autoloading', 'dependency'],
                'one' => 'Composer declares what your project needs, downloads it, and registers an autoloader so classes appear when referenced.',
                'why' => [
                    'Shared code should be a versioned dependency, not a copy-paste.',
                    'PSR-4 autoloading maps namespaces to directories automatically.',
                    'composer.lock pins exact versions so deployments match dev machines.',
                ],
                'tip' => 'Run composer dump-autoload after adding classes in new namespaces - the map is generated, not guessed.',
                'learn' => [
                    'Read composer.json and composer.lock.',
                    'Install and update dependencies.',
                    'Understand PSR-4 namespace mapping.',
                    'Use the vendor/autoload.php entry point.',
                ],
                'prereqs' => ['Functions and classes basics', 'Composer installed'],
                'bridge' => 'Inspect a project manifest, then require a package and load it.',
                'code' => <<<'CODE'
<?php
require __DIR__ . '/vendor/autoload.php';

// A PSR-4 class is now resolvable by namespace:
// $client = new Vendor\Package\Client();

$manifest = json_decode(
    file_get_contents(__DIR__ . '/composer.json'),
    true,
);

echo 'php constraint: ', $manifest['require']['php'] ?? 'n/a', PHP_EOL;

CODE,
                'output' => 'php constraint: >=8.2',
                'walk' => 'autoload.php is the single require that loads every dependency lazily: when a class name is first used, the autoloader looks up its namespace prefix and includes the mapped file. The manifest read is just JSON - composer.json is an ordinary file.',
                'table_headers' => ['Command', 'Does', 'When'],
                'table_rows' => [
                    ['composer install', 'reads lock, installs exact versions', 'fresh checkout / CI'],
                    ['composer update', 'resolves new versions, rewrites lock', 'deliberately upgrading'],
                    ['composer require', 'adds a dependency', 'adopting a package'],
                    ['dump-autoload', 'regenerates the autoloader', 'after namespace moves'],
                ],
                'panel' => [
                    'book' => 'The book treats Composer as assumed infrastructure for project work.',
                    'modern' => 'Composer 2 does parallel downloads and audits; the workflow is unchanged.',
                    'why' => 'One manifest plus lock file is the reproducibility contract.',
                ],
                'exercise' => 'Add a dev dependency and show which file pins its exact version.',
                'quiz' => 'Which file must change when a dependency version is updated?',
                'cards' => ['composer.lock', 'PSR-4 autoloading'],
                'takeaways' => [
                    'composer.json declares; lock pins.',
                    'Autoloading resolves classes on demand.',
                    'install in CI, update on purpose.',
                ],
                'closing' => 'Last foundation: running PHP from the terminal like a professional toolchain.',
            ],
            [
                'slug' => 'foundations-cli',
                'title' => 'The PHP CLI toolchain',
                'summary' => 'Run scripts, pass arguments, read exit codes, and pipe input/output from the terminal.',
                'est' => 11,
                'concepts' => ['cli', 'exit-code', 'argv'],
                'one' => 'From the terminal, PHP is a program that reads argv, prints to stdout, and reports success through its exit code.',
                'why' => [
                    'Scripts, tests and CI all run this way - exit codes are the contract.',
                    'argv gives positional arguments without any web layer.',
                    'Piping stdout/stderr turns small tools into pipelines.',
                ],
                'tip' => 'exit(0) means success; any non-zero code means failure - CI relies on exactly that.',
                'learn' => [
                    'Run a file and pass arguments.',
                    'Read $argv safely.',
                    'Return meaningful exit codes.',
                    'Separate stdout from stderr.',
                ],
                'prereqs' => ['Basic syntax', 'A terminal'],
                'bridge' => 'Write a script that takes a name argument and fails clearly without one.',
                'code' => <<<'CODE'
<?php
$name = $argv[1] ?? null;

if ($name === null) {
    fwrite(STDERR, "usage: greet.php <name>\n");
    exit(2);
}

echo "hello {$name}\n";
exit(0);

CODE,
                'output' => 'greet.php Ada -> hello Ada; no argument -> usage on stderr, exit 2',
                'walk' => 'STDERR is a constant stream separate from echo, so pipelines can capture the error while stdout stays clean. exit(2) marks a usage mistake distinctly from a crash (1) or success (0) - shell scripts and CI branch on exactly these numbers.',
                'table_headers' => ['Exit code', 'Meaning', 'Who checks'],
                'table_rows' => [
                    ['0', 'success', 'shell, CI'],
                    ['1', 'runtime failure', 'shell, CI'],
                    ['2', 'usage mistake', 'callers, docs'],
                    ['-', 'stdout carries data', 'pipes'],
                ],
                'panel' => [
                    'book' => 'The book uses CLI scripts for builds and test runs in later chapters.',
                    'modern' => 'Artisan-style command runners wrap this same argv/exit contract.',
                    'why' => 'Anything automatable speaks exit codes.',
                ],
                'exercise' => 'Make greet.php accept --shout and exit 1 when the name contains only digits.',
                'quiz' => 'Which stream should a usage message go to, and why?',
                'cards' => ['exit codes', 'argv / STDIN / STDERR'],
                'takeaways' => [
                    'argv carries arguments; exit codes carry results.',
                    'stdout for data, stderr for diagnostics.',
                    'Same contract in tests and CI.',
                ],
                'closing' => 'Foundations done - the book now builds objects on exactly this ground.',
            ],
        ];
    }
}
