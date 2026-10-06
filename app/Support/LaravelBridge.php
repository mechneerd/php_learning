<?php

namespace App\Support;

/**
 * PHP -> Laravel bridge rows (docs/05 §4): each book-era pattern mapped to
 * where it lives in Laravel, tagged with the stage where it should appear.
 * Lesson pages reuse the partial with rows filtered to the lesson's stage.
 */
final class LaravelBridge
{
    /**
     * @var list<array{core: string, laravel: string, stages: list<int>}>
     */
    public const ROWS = [
        ['core' => 'PHP classes / constructor promotion', 'laravel' => 'Controllers, Services, Models', 'stages' => [2]],
        ['core' => 'Interfaces', 'laravel' => 'Illuminate\\Contracts\\*', 'stages' => [2, 5]],
        ['core' => 'Dependency Injection (Ch 9)', 'laravel' => 'Service Container & method injection', 'stages' => [5]],
        ['core' => 'Namespaces (Ch 5)', 'laravel' => 'App\\ structure, PSR-4', 'stages' => [3]],
        ['core' => 'Autoload + Composer (Ch 5/16)', 'laravel' => 'composer.json, package discovery', 'stages' => [3, 9]],
        ['core' => 'Exceptions (Ch 4)', 'laravel' => 'app/Exceptions, report/render', 'stages' => [2]],
        ['core' => 'Front Controller (Ch 12)', 'laravel' => 'public/index.php + router', 'stages' => [8]],
        ['core' => 'Application Controller / Page Controller (Ch 12)', 'laravel' => 'Route + Controller', 'stages' => [8]],
        ['core' => 'Template View + View Helper (Ch 12)', 'laravel' => 'Blade + View Composers', 'stages' => [8]],
        ['core' => 'Transaction Script vs Domain Model (Ch 12)', 'laravel' => 'Eloquent vs Service layer', 'stages' => [8]],
        ['core' => 'Data Mapper / Unit of Work / Identity Map (Ch 13)', 'laravel' => 'Eloquent ORM internals', 'stages' => [8]],
        ['core' => 'Registry (Ch 12)', 'laravel' => 'Facades / container singletons (with caution)', 'stages' => [8]],
        ['core' => 'Observer (Ch 11)', 'laravel' => 'Model events, listeners', 'stages' => [7]],
        ['core' => 'Strategy (Ch 11)', 'laravel' => 'Driver pattern in config', 'stages' => [7]],
        ['core' => 'Command (Ch 11)', 'laravel' => 'Jobs & Queues', 'stages' => [7]],
        ['core' => 'PSR-4 (Ch 15)', 'laravel' => 'autoload config', 'stages' => [9]],
        ['core' => 'PHPUnit (Ch 18)', 'laravel' => 'php artisan test, Pest', 'stages' => [9]],
        ['core' => 'CI (Ch 21)', 'laravel' => 'GitHub Actions workflow', 'stages' => [10]],
    ];

    /**
     * Rows whose stages include the given stage number.
     *
     * @return list<array{core: string, laravel: string}>
     */
    public static function forStage(int $stage): array
    {
        $rows = [];

        foreach (self::ROWS as $row) {
            if (in_array($stage, $row['stages'], true)) {
                $rows[] = ['core' => $row['core'], 'laravel' => $row['laravel']];
            }
        }

        return $rows;
    }
}
