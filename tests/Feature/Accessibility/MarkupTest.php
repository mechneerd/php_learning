<?php

use Illuminate\Support\Facades\File;

/**
 * Static accessibility rules for every Blade template (docs/15 Phase 10):
 * focus outlines may only be removed when replaced by a focus-visible
 * ring, and Livewire actions must live on semantic elements.
 */
it('never hides focus outlines without a focus-visible replacement', function () {
    $violations = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if ($file->getExtension() !== 'blade.php') {
            continue;
        }

        foreach (explode("\n", $file->getContents()) as $index => $line) {
            if (preg_match('/\b(outline-none|outline-hidden)\b/', $line) === 1 && ! str_contains($line, 'focus-visible:')) {
                $violations[] = $file->getFilename().':'.($index + 1).' '.$line;
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});

it('never puts wire:click on non-interactive elements', function () {
    $violations = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if ($file->getExtension() !== 'blade.php') {
            continue;
        }

        foreach (explode("\n", $file->getContents()) as $index => $line) {
            if (preg_match('/<(div|span|li|p|a)(\s[^>]*)?wire:click/', $line) === 1) {
                $violations[] = $file->getFilename().':'.($index + 1).' '.$line;
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});
