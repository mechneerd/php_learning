<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'PHP 8 Objects, Patterns, and Practice',
            'subtitle' => 'Mastering OO Enhancements, Design Patterns, and Essential Development Tools',
            'author' => 'Matt Zandstra',
            'edition' => '6th',
            'isbn' => '978-1-4842-6791-2',
            'publisher' => 'Apress',
            'published_year' => 2021,
            'status' => BookStatus::Active,
        ];
    }
}
