<?php

use App\Services\Import\TextSanitizer;

it('strips control characters and keeps readable text', function () {
    $clean = TextSanitizer::clean("Hello\x07 world\x08!\nSecond line");

    expect($clean)->toBe("Hello world!\nSecond line");
});

it('normalises non breaking spaces', function () {
    expect(TextSanitizer::clean("a\u{00A0}b"))->toBe('a b');
});

it('turns replacement glyphs into spaces', function () {
    expect(TextSanitizer::clean("Chapter 3\u{FFFD}\u{FFFD}\u{FFFD}Object Basics"))
        ->toBe('Chapter 3   Object Basics');
});

it('collapses runs of blank lines', function () {
    $clean = TextSanitizer::clean("one\n\n\n\n\ntwo\n\n\nthree");

    expect($clean)->toBe("one\n\ntwo\n\nthree");
});
