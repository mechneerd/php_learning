<?php

use App\Services\Import\PrintedPageDetector;

it('reads the folio from the last numeric line', function () {
    $lines = [
        'Some paragraph text that wraps over lines.',
        'And more of it.',
        '                                                 45',
    ];

    expect(PrintedPageDetector::fromLines($lines))->toBe(45);
});

it('skips publisher furniture below the folio', function () {
    $lines = [
        'CHAPTER 1',
        '                                                 3',
        '© Matt Zandstra 2021',
        'M. Zandstra, PHP 8 Objects, Patterns, and Practice, https://doi.org/10.1007/978-1-4842-6791-2_1',
    ];

    expect(PrintedPageDetector::fromLines($lines))->toBe(3);
});

it('returns null when the page ends in prose', function () {
    expect(PrintedPageDetector::fromText('A paragraph without a folio.'))->toBeNull();
});

it('returns null for a blank page', function () {
    expect(PrintedPageDetector::fromText(''))->toBeNull();
});
