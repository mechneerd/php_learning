<?php

use App\Services\Execution\BannedFunctionCheck;

it('flags each banned function from docs/12', function (string $code, string $expected) {
    expect((new BannedFunctionCheck)->firstHit($code))->toBe($expected);
})->with([
    ['<?php eval($x);', 'eval'],
    ['<?php assert($x);', 'assert'],
    ['<?php system("ls");', 'system'],
    ['<?php exec("ls");', 'exec'],
    ['<?php shell_exec("ls");', 'shell_exec'],
    ['<?php passthru("ls");', 'passthru'],
    ['<?php proc_open("ls", $d, $p);', 'proc_open'],
    ['<?php popen("ls", "r");', 'popen'],
    ['<?php pcntl_fork();', 'pcntl_fork'],
    ['<?php pcntl_exec("/bin/sh");', 'pcntl_exec'],
    ['<?php curl_exec($ch);', 'curl_exec'],
    ['<?php fsockopen("example.com", 80);', 'fsockopen'],
    ['<?php dl("x.so");', 'dl'],
    ['<?php putenv("X=1");', 'putenv'],
    ['<?php mail("a@b.c", "s", "b");', 'mail'],
    ['<?php file_get_contents("https://example.com");', 'file_get_contents(http…)'],
    ['<?php include "https://example.com/x.php";', 'remote include'],
    ['<?php require_once "http://example.com/x.php";', 'remote include'],
    ['<?php SYSTEM("ls");', 'system'],
]);

it('allows normal exercise code', function () {
    $code = <<<'PHP'
        <?php

        class Greeter
        {
            public function greeting(string $name): string
            {
                return 'Hello '.$name;
            }
        }

        echo (new Greeter)->greeting('World');
        PHP;

    expect((new BannedFunctionCheck)->firstHit($code))->toBeNull();
});
