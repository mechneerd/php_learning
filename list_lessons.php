<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Concept;
use App\Models\Lesson;

foreach (Lesson::where('slug', 'like', 'ch3-%')->orderBy('ord')->get() as $l) {
    $concepts = $l->concepts()->pluck('name')->implode(', ');
    echo $l->ord.'|'.$l->slug.'|'.$l->title.'|'.$concepts.PHP_EOL;
}
echo '---CONCEPTS TOTAL: '.Concept::count().PHP_EOL;