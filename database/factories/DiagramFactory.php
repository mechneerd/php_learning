<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\DiagramKind;
use App\Enums\DiagramSource;
use App\Models\Diagram;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagram>
 */
class DiagramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'kind' => DiagramKind::Flowchart,
            'title' => fake()->sentence(3),
            'mermaid_source' => "flowchart TD\n    A[Start] --> B[End]",
            'source' => DiagramSource::Ai,
            'figure_ref' => null,
            'page_pdf' => null,
            'status' => ContentStatus::Published,
        ];
    }
}
