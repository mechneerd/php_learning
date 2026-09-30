<?php

use App\Services\Import\TocParser;

function tocParserFixture(): string
{
    $line = fn (string $title, string $page): string => $title.str_repeat("\u{FFFD}", 40).$page;

    return implode("\n", [
        'Table of Contents',
        $line('About the Author ', '  xix'),
        $line('Part I:  Objects ', '  1'),
        $line('Chapter 1:  PHP: Design and Management ', '  3'),
        '  '.$line('The Problem ', '  3'),
        '  '.$line('About This Book ', '  8'),
        '       '.$line('Objects ', '  8'),
        '       '.$line('Patterns ', '  9'),
        '  '.$line('Summary ', '  11'),
        $line('Chapter 2:  PHP and Objects ', '  13'),
        '  '.$line('The Accidental Success of PHP Objects ', '  13'),
        '  '.$line('Objects and Classes ', '  15'),
        '  '.$line('Summary ', '  19'),
        $line('Index ', '  779'),
    ]);
}

it('parses chapters, parts, sections, and ignores front matter and the index', function () {
    $entries = (new TocParser)->parse(tocParserFixture());

    $kinds = collect($entries)->pluck('kind');

    expect($entries)->toHaveCount(11)
        ->and($kinds->filter(fn ($kind) => $kind === 'chapter'))->toHaveCount(2)
        ->and($kinds->filter(fn ($kind) => $kind === 'part'))->toHaveCount(1)
        ->and($kinds->filter(fn ($kind) => $kind === 'section'))->toHaveCount(8)
        ->and(collect($entries)->pluck('title'))->not->toContain('Index');
});

it('derives nested section levels relative to each chapter', function () {
    $parser = new TocParser;
    $chapters = $parser->chapters($parser->parse(tocParserFixture()));

    expect($chapters)->toHaveCount(2);

    $first = $chapters[0];

    expect($first['number'])->toBe(1)
        ->and($first['page'])->toBe(3)
        ->and($first['sections'])->toHaveCount(5)
        ->and($first['sections'][0])->toMatchArray(['title' => 'The Problem', 'level' => 1, 'parent' => null])
        ->and($first['sections'][2])->toMatchArray(['title' => 'Objects', 'level' => 2, 'parent' => 1])
        ->and($first['sections'][3])->toMatchArray(['title' => 'Patterns', 'level' => 2, 'parent' => 1])
        ->and($first['sections'][4])->toMatchArray(['title' => 'Summary', 'level' => 1, 'parent' => null])
        ->and($chapters[1]['sections'])->toHaveCount(3);
});

it('stops at the first prose after the toc', function () {
    $text = tocParserFixture()."\n\nSuddenly a sentence with no leaders on it.\nAnother line here.\nThird line.";
    $entries = (new TocParser)->parse($text);

    expect($entries)->toHaveCount(11);
});
