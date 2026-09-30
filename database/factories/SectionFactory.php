<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'parent_id' => null,
            'number' => null,
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'level' => 1,
            'page_printed_from' => null,
            'page_printed_to' => null,
            'page_pdf_from' => null,
            'page_pdf_to' => null,
            'ord' => 0,
        ];
    }
}
