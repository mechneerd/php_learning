<?php

namespace Database\Factories;

use App\Enums\PdfDocumentStatus;
use App\Models\Book;
use App\Models\PdfDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdfDocument>
 */
class PdfDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'original_name' => 'php-8-objects.pdf',
            'path' => 'books/'.fake()->sha256().'.pdf',
            'sha256' => fake()->sha256(),
            'page_count' => 10,
            'status' => PdfDocumentStatus::Extracted,
            'error' => null,
        ];
    }
}
