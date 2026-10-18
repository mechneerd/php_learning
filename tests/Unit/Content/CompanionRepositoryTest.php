<?php

use App\Services\Content\CompanionRepository;
use Tests\TestCase;

uses(TestCase::class);

it('indexes listing markers from a companion repository', function () {
    $root = sys_get_temp_dir().'/companion_'.uniqid();
    mkdir($root.'/src/ch13/batch01', 0777, true);
    file_put_contents($root.'/src/ch13/batch01/SpaceMapper.php', "<?php\n/* listing 13.16 */\nclass SpaceMapper {}\n/* /listing 13.16 */\n");
    file_put_contents($root.'/src/ch13/batch01/Runner.php', "<?php\n// listing 13.99\n");

    config(['import.companion_path' => $root]);

    $repo = new CompanionRepository;

    expect($repo->root())->toBe($root)
        ->and($repo->locate('13.16'))->toBe('src/ch13/batch01/SpaceMapper.php#listing-13.16')
        ->and($repo->locate('99.99'))->toBeNull();

    @unlink($root.'/src/ch13/batch01/SpaceMapper.php');
    @unlink($root.'/src/ch13/batch01/Runner.php');
    @rmdir($root.'/src/ch13/batch01');
    @rmdir($root.'/src/ch13');
    @rmdir($root.'/src');
    @rmdir($root);
});

it('fails soft when the companion repository is absent', function () {
    config(['import.companion_path' => sys_get_temp_dir().'/does_not_exist_'.uniqid()]);

    $repo = new CompanionRepository;

    expect($repo->locate('1.23'))->toBeNull()
        ->and($repo->markerIndex())->toBe([]);
});
