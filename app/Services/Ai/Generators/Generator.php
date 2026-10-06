<?php

namespace App\Services\Ai\Generators;

use App\Models\Book;
use App\Services\Ai\GenerationRunner;
use App\Services\Ai\PromptRunner;

/**
 * Shared prompt plumbing for Pipeline A generators: the fixed system
 * preamble (book attribution, faithful-use and JSON-only rules) and the
 * idempotency hash helper. Each concrete generator supplies its rules
 * and DTO validator (docs/10 output contract).
 */
abstract class Generator
{
    public function __construct(
        protected readonly GenerationRunner $runner,
        protected readonly PromptRunner $helpers,
    ) {}

    /**
     * @param  list<string>  $parts
     */
    protected function hash(string $task, array $parts): string
    {
        return $this->helpers->hashFor($task, $parts);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected function system(string $sourceLine, string $rules, array $schema): string
    {
        $book = Book::query()->value('title')
            ?? 'PHP 8 Objects, Patterns, and Practice';

        return "You are a PHP curriculum writer working from the book \"{$book}\".\n"
            ."Source: {$sourceLine}\n"
            ."Rules:\n"
            ."- Use the given source faithfully; mark anything you add as teaching scaffolding.\n"
            ."- Never invent quotes or page numbers.\n"
            ."- Output only a single JSON object matching this schema - no prose, no code fences:\n"
            .json_encode($schema, JSON_UNESCAPED_SLASHES)
            .($rules !== '' ? "\n- {$rules}" : '');
    }
}
