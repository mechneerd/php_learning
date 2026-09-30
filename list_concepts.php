<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Lesson;

$rows = DB::table('lesson_concepts')
    ->join('concepts', 'concepts.id', '=', 'lesson_concepts.concept_id')
    ->join('lessons', 'lessons.id', '=', 'lesson_concepts.lesson_id')
    ->where('lessons.slug', 'like', 'ch3-%')
    ->orderBy('lessons.ord')
    ->get(['lessons.slug as lslug', 'concepts.slug as cslug', 'concepts.name as cname']);
foreach ($rows as $r) {
    echo $r->lslug.' => '.$r->cslug.' ('.$r->cname.')'.PHP_EOL;
}
echo 'unique concepts: '.collect($rows)->pluck('cslug')->unique()->count().PHP_EOL;