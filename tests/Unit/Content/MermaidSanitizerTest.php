<?php

use App\Services\Content\MermaidSanitizer;

it('strips click handlers and javascript uris', function () {
    $sanitizer = new MermaidSanitizer;

    $source = "flowchart TD\n    A --> B\n    click A href \"javascript:alert(1)\"\n";

    $clean = $sanitizer->sanitize($source);

    expect($clean)->not->toContain('click A')
        ->not->toContain('javascript:')
        ->toContain('A --> B');
});

it('normalizes line endings and collapses blank runs', function () {
    $sanitizer = new MermaidSanitizer;

    expect($sanitizer->sanitize("a\r\n\r\n\r\n\r\nb"))->toBe("a\n\nb");
});

it('caps oversized diagrams', function () {
    $sanitizer = new MermaidSanitizer;

    $clean = $sanitizer->sanitize(str_repeat('flowchart TD\nA --> B\n', 5000));

    expect(strlen($clean))->toBeLessThanOrEqual(MermaidSanitizer::MAX_LENGTH);
});

it('treats blank sources as unrenderable', function () {
    $sanitizer = new MermaidSanitizer;

    expect($sanitizer->sanitize("   \n  "))->toBe('')
        ->and($sanitizer->isRenderable("   \n  "))->toBeFalse()
        ->and($sanitizer->isRenderable('flowchart TD\n A --> B'))->toBeTrue();
});
