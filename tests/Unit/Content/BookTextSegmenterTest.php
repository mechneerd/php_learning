<?php

use App\Services\Content\BookTextSegmenter;

beforeEach(function () {
    $this->seg = new BookTextSegmenter;
    $this->ctx = [
        'chapter_number' => 3,
        'chapter_title' => 'Object Basics',
        'section_title' => 'A First Class',
        'is_chapter_start' => false,
    ];
});

it('strips running headers, folios and copyright lines during clean', function () {
    $clean = $this->seg->clean([
        [
            'pdf' => 24,
            'printed' => 23,
            'text' => implode("\n", [
                'PHP 8 Objects, Patterns, and Practice',
                '',
                '    The problem is that PHP is just too easy.',
                '',
                '24',
                '© Matt Zandstra 2021',
            ]),
        ],
    ]);

    expect($clean[0]['text'])
        ->not->toContain('PHP 8 Objects, Patterns')
        ->not->toContain('Matt Zandstra 2021')
        ->toContain('The problem is that PHP is just too easy.');
});

it('repairs dropped capitals and letter-spaced words', function () {
    $clean = $this->seg->clean([
        [
            'pdf' => 1,
            'printed' => 1,
            'text' => implode("\n", [
                'C',
                'lasses are templates from which objects are made.',
                'T he class keyword introduces the new type.',
            ]),
        ],
    ]);

    expect($clean[0]['text'])
        ->toContain('Classes are templates')
        ->not->toContain("\nC\n")
        ->toContain('The class keyword');
});

it('merges a paragraph across a page break and stamps both pages', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 10,
            'printed' => 9,
            'text' => implode("\n", [
                'PHP 8 Objects, Patterns, and Practice',
                '',
                '    The team met the new',
                '9',
            ]),
        ],
        [
            'pdf' => 11,
            'printed' => 10,
            'text' => implode("\n", [
                'PHP 8 Objects, Patterns, and Practice',
                '',
                '    requirements the next morning and began to plan the migration.',
                '10',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(1)
        ->and($seg[0]['type'])->toBe('paragraph')
        ->and($seg[0]['text'])->toContain('met the new requirements the next morning')
        ->and($seg[0]['page_pdf_from'])->toBe(10)
        ->and($seg[0]['page_pdf_to'])->toBe(11)
        ->and($seg[0]['page_printed_from'])->toBe(9)
        ->and($seg[0]['page_printed_to'])->toBe(10);
});

it('joins hyphen-wrapped words without inserting a space', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 5,
            'printed' => 5,
            'text' => implode("\n", [
                '    The design was shaped by object-',
                '    oriented programming ideas that spread quickly.',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(1)
        ->and($seg[0]['type'])->toBe('paragraph')
        ->and($seg[0]['text'])->toContain('object-oriented')
        ->not->toContain('object- oriented');
});

it('pairs a listing marker with its code and stamps the listing ref', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 42,
            'printed' => 41,
            'text' => implode("\n", [
                '    In short, a class is a code template used to generate objects:',
                '',
                '// listing 03.01',
                'class ShopProduct',
                '{',
                '    // class body',
                '}',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(2)
        ->and($seg[0]['type'])->toBe('paragraph')
        ->and($seg[1]['type'])->toBe('code')
        ->and($seg[1]['listing_ref'])->toBe('03.01')
        ->and($seg[1]['text'])->toContain('class ShopProduct')
        ->not->toContain('listing 03.01');
});

it('emits an output block after an announced intro and keeps the trailing Note as a callout', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 43,
            'printed' => 42,
            'text' => implode("\n", [
                '    Here is the script. It gives the following output:',
                '',
                'object(ShopProduct)#1 (1) {',
                '  ["title"] =>',
                '  string(9) "Starship"',
                '}',
                '',
                'Note that the class is already legal PHP code.',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(3)
        ->and($seg[0]['type'])->toBe('paragraph')
        ->and($seg[0]['text'])->toContain('gives the following output')
        ->and($seg[1]['type'])->toBe('output')
        ->and($seg[1]['text'])->toContain('object(ShopProduct)#1')
        ->not->toContain('gives the following output')
        ->and($seg[2]['type'])->toBe('callout')
        ->and($seg[2]['text'])->toContain('Note that the class');
});

it('groups consecutive bullet lines into a bullets block', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 24,
            'printed' => 23,
            'text' => implode("\n", [
                '• The evolution of disaster: A project goes bad',
                '• Design and PHP: how techniques took root',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(1)
        ->and($seg[0]['type'])->toBe('bullets')
        ->and($seg[0]['items'])->toHaveCount(2)
        ->and($seg[0]['items'][0])->toContain('evolution of disaster')
        ->and($seg[0]['items'][1])->toContain('Design and PHP');
});

it('splits a flush lead heading away from its paragraph', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 41,
            'printed' => 40,
            'text' => implode("\n", [
                'Objects and classes',
                'The world of software is built from components that share a common description, and PHP is no exception to this rule.',
            ]),
        ],
    ], $this->ctx);

    expect($seg)->toHaveCount(2)
        ->and($seg[0]['type'])->toBe('heading')
        ->and($seg[0]['text'])->toBe('Objects and classes')
        ->and($seg[1]['type'])->toBe('paragraph')
        ->and($seg[1]['text'])->toContain('The world of software');
});

it('turns a chapter opener into a chapter heading with the title consumed', function () {
    $seg = $this->seg->segment([
        [
            'pdf' => 24,
            'printed' => 23,
            'text' => implode("\n", [
                'CHAPTER 1',
                '',
                'PHP: Design and',
                'Management',
                'In July 2004, PHP 5.0 was released and object-oriented programming became a serious concern for the community at large.',
                '',
                '• The evolution of disaster: A project goes bad',
                '23',
            ]),
        ],
    ], [
        'chapter_number' => 1,
        'chapter_title' => 'PHP: Design and Management',
        'section_title' => 'The Problem',
        'is_chapter_start' => true,
    ]);

    expect($seg)->toHaveCount(3)
        ->and($seg[0]['type'])->toBe('heading')
        ->and($seg[0]['text'])->toBe('Chapter 1 - PHP: Design and Management')
        ->and($seg[1]['type'])->toBe('paragraph')
        ->and($seg[1]['text'])->toContain('In July 2004, PHP 5.0 was released')
        ->not->toContain('PHP: Design and Management')
        ->and($seg[2]['type'])->toBe('bullets')
        ->and($seg[2]['items'])->toHaveCount(1);
});
