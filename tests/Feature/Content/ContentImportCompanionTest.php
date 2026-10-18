<?php

use App\Models\CodeExample;
use App\Models\User;
use Tests\Support\LessonFixture;

/**
 * Builds a throwaway companion repo containing one marker and points
 * config at it.
 *
 * @return string root path
 */
function companion_fixture_repo(): string
{
    $root = sys_get_temp_dir().'/companion_cmd_'.uniqid();
    mkdir($root.'/src/ch03/batch01', 0777, true);
    file_put_contents(
        $root.'/src/ch03/batch01/Runner.php',
        "<?php\n/* listing 03.01 */\n\$x = 1;\n/* /listing 03.01 */\n"
    );
    config(['import.companion_path' => $root]);

    return $root;
}

function companion_cleanup_repo(string $root): void
{
    @unlink($root.'/src/ch03/batch01/Runner.php');
    @rmdir($root.'/src/ch03/batch01');
    @rmdir($root.'/src/ch03');
    @rmdir($root.'/src');
    @rmdir($root);
}

it('links code listings to companion files', function () {
    $root = companion_fixture_repo();
    $lesson = LessonFixture::full();

    $this->artisan('content:import-companion')
        ->expectsOutputToContain('updated=1')
        ->assertSuccessful();

    $example = CodeExample::query()->where('listing_ref', '03.01')->sole();

    expect($example->external_ref)->toBe('src/ch03/batch01/Runner.php#listing-03.01');

    $this->artisan('content:import-companion')
        ->expectsOutputToContain('updated=0 unchanged=1')
        ->assertSuccessful();

    companion_cleanup_repo($root);
    expect($lesson->exists)->toBeTrue();
});

it('reports failure without a companion repository', function () {
    config(['import.companion_path' => sys_get_temp_dir().'/nope_'.uniqid()]);

    $response = $this->artisan('content:import-companion');

    $response->assertFailed();
});

it('renders the companion source file on the lesson page', function () {
    $lesson = LessonFixture::full();
    $example = CodeExample::query()->where('listing_ref', '03.01')->sole();
    $example->update(['external_ref' => 'src/ch03/batch01/Runner.php#listing-03.01']);

    $this->actingAs(User::factory()->create())
        ->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertSee('Runner.php')
        ->assertSee('src/ch03/batch01/Runner.php#listing-03.01', false);
});
