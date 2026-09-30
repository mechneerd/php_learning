<?php

namespace Database\Factories;

use App\Models\PdfDocument;
use App\Models\PdfPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdfPage>
 */
class PdfPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pdf_document_id' => PdfDocument::factory(),
            'page_pdf' => fake()->unique()->numberBetween(1, 1000),
            'page_printed' => null,
            'text' => "Chapter 1\n\nA paragraph of extracted text for the page.",
            'word_count' => 9,
            'extraction_quality' => 1.0,
            'needs_review' => false,
        ];
    }
}
