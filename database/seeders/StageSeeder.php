<?php

namespace Database\Seeders;

use App\Enums\StageSource;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * The 12 teaching stages from docs/04-learning-architecture.md.
     * Stage 0 is AI-generated (not in the book); the rest map onto book chapters.
     *
     * gate_rules come from docs/05-knowledge-map.md section 2 (null = ungated).
     *
     * @var list<array{number: int, slug: string, name: string, subtitle: string, source: StageSource, gate_rules: list<array<string, mixed>>|null, description: string}>
     */
    private const STAGES = [
        ['number' => 0, 'slug' => 'php-foundations', 'name' => 'PHP Foundations', 'subtitle' => 'Language basics the book assumes', 'source' => StageSource::Ai, 'gate_rules' => null, 'description' => 'Variables and types, control flow, arrays, functions, superglobals, sessions, files, errors, PDO, Composer and CLI tools. Generated content, never attributed to the book.'],
        ['number' => 1, 'slug' => 'orientation', 'name' => 'Orientation: why design matters', 'subtitle' => 'Book chapters 1–2', 'source' => StageSource::Book, 'gate_rules' => null, 'description' => 'Why projects fail, the accidental success of PHP objects, and how the book is organised.'],
        ['number' => 2, 'slug' => 'object-core', 'name' => 'Object Core', 'subtitle' => 'Book chapters 3–4', 'source' => StageSource::Book, 'gate_rules' => [['quiz' => 'php-foundations', 'min_score' => 80], ['exercises' => 10]], 'description' => 'Classes and objects, constructors, visibility, inheritance, abstract classes, interfaces, traits, and exceptions.'],
        ['number' => 3, 'slug' => 'object-tools-design', 'name' => 'Object Tools & Design', 'subtitle' => 'Book chapters 5–6', 'source' => StageSource::Mixed, 'gate_rules' => null, 'description' => 'Namespaces, attributes, closures, generators, reflection, plus OOP design principles such as SOLID.'],
        ['number' => 4, 'slug' => 'pattern-principles', 'name' => 'Pattern Principles', 'subtitle' => 'Book chapters 7–8', 'source' => StageSource::Book, 'gate_rules' => [['concept' => 'inheritance', 'min_level' => 'practicing'], ['concept' => 'interface', 'min_level' => 'practicing'], ['concept' => 'trait', 'min_level' => 'practicing'], ['concept' => 'exception', 'min_level' => 'practicing']], 'description' => 'What patterns are, how to describe them, and the principle of favouring composition over inheritance.'],
        ['number' => 5, 'slug' => 'creational-patterns', 'name' => 'Creational Patterns', 'subtitle' => 'Book chapter 9', 'source' => StageSource::Book, 'gate_rules' => [['concept' => 'composition', 'min_level' => 'practicing'], ['concept' => 'encapsulation', 'min_level' => 'practicing'], ['concept' => 'coupling', 'min_level' => 'practicing']], 'description' => 'Factory, builder, prototype, and singleton patterns in PHP.'],
        ['number' => 6, 'slug' => 'structural-patterns', 'name' => 'Structural Patterns', 'subtitle' => 'Book chapter 10', 'source' => StageSource::Book, 'gate_rules' => null, 'description' => 'Adapter, bridge, composite, decorator, facade, proxy, and related structures.'],
        ['number' => 7, 'slug' => 'behavioral-patterns', 'name' => 'Behavioral Patterns', 'subtitle' => 'Book chapter 11', 'source' => StageSource::Book, 'gate_rules' => null, 'description' => 'Observer, strategy, command, iterator, chain of responsibility, and template method.'],
        ['number' => 8, 'slug' => 'architecture-persistence', 'name' => 'Architecture & Persistence', 'subtitle' => 'Book chapters 12–13', 'source' => StageSource::Book, 'gate_rules' => [['concept' => 'di', 'min_level' => 'comfortable'], ['concept' => 'factory', 'min_level' => 'comfortable'], ['concept' => 'strategy', 'min_level' => 'comfortable']], 'description' => 'Web patterns, controllers, views, domain models, data mappers, and transaction scripts.'],
        ['number' => 9, 'slug' => 'professional-practice', 'name' => 'Professional Practice', 'subtitle' => 'Book chapters 14–18', 'source' => StageSource::Book, 'gate_rules' => [['concept' => 'namespace', 'min_level' => 'mastered'], ['concept' => 'autoloading', 'min_level' => 'mastered'], ['exercises' => 1]], 'description' => 'Objects and patterns in the wider project: version control, testing, and automated builds.'],
        ['number' => 10, 'slug' => 'build-delivery', 'name' => 'Build & Delivery', 'subtitle' => 'Book chapters 19–21', 'source' => StageSource::Mixed, 'gate_rules' => null, 'description' => 'Packaging and deploying PHP applications, plus modern CI/CD delivery panels.'],
        ['number' => 11, 'slug' => 'synthesis-capstone', 'name' => 'Synthesis & Capstone', 'subtitle' => 'Book chapter 22 + Appendix B', 'source' => StageSource::Book, 'gate_rules' => [['comfortable_pct' => 60], ['mastered_pct' => 20]], 'description' => 'Patterns and practice in the wild, plus the capstone project and interview set.'],
    ];

    public function run(): void
    {
        foreach (self::STAGES as $stage) {
            Stage::updateOrCreate(
                ['number' => $stage['number']],
                [
                    'slug' => $stage['slug'],
                    'name' => $stage['name'],
                    'subtitle' => $stage['subtitle'],
                    'source' => $stage['source'],
                    'gate_rules' => $stage['gate_rules'],
                    'description' => $stage['description'],
                    'ord' => $stage['number'],
                ],
            );
        }
    }
}
