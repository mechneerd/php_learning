<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('figure_ref', 20);
            $table->text('caption')->nullable();
            $table->text('alt');
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Provenance block
            $table->string('source', 20)->default('book');
            $table->unsignedSmallInteger('page_printed')->nullable();
            $table->unsignedSmallInteger('page_pdf');
            $table->timestamps();

            $table->unique('figure_ref');
            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_media');
    }
};
