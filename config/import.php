<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF extraction binaries (Poppler)
    |--------------------------------------------------------------------------
    |
    | Used by App\Services\Import\PdfTextExtractor. When null the importer
    | auto-detects the binaries on PATH (and common install locations).
    |
    */

    'pdftotext_binary' => env('PDFTOTEXT_BINARY'),

    'pdfinfo_binary' => env('PDFINFO_BINARY'),

    'book_title' => env('BOOK_TITLE', 'PHP 8 Objects, Patterns, and Practice'),

    'detected_paths' => [
        'pdftotext' => [
            'windows' => [
                'C:/poppler/Library/bin/pdftotext.exe',
                'C:/Program Files/poppler/Library/bin/pdftotext.exe',
            ],
            'unix' => [
                '/usr/bin/pdftotext',
                '/opt/homebrew/bin/pdftotext',
            ],
        ],
        'pdfinfo' => [
            'windows' => [
                'C:/poppler/Library/bin/pdfinfo.exe',
                'C:/Program Files/poppler/Library/bin/pdfinfo.exe',
            ],
            'unix' => [
                '/usr/bin/pdfinfo',
                '/opt/homebrew/bin/pdfinfo',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import limits
    |--------------------------------------------------------------------------
    */

    'max_upload_mb' => (int) env('PDF_MAX_UPLOAD_MB', 50),

    // A page whose extracted text falls below this word count is flagged
    // for manual review (diagrams, scans, broken extraction).
    'min_words_per_page' => (int) env('PDF_MIN_WORDS_PER_PAGE', 3),

    'extract_quality_threshold' => (float) env('PDF_QUALITY_THRESHOLD', 0.6),
];
