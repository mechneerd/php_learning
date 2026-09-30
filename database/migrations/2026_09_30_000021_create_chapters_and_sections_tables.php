<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('title');
            $table->string('slug');
            $table->unsignedSmallInteger('page_printed_from')->nullable();
            $table->unsignedSmallInteger('page_printed_to')->nullable();
            $table->unsignedSmallInteger('page_pdf_from')->nullable();
            $table->unsignedSmallInteger('page_pdf_to')->nullable();
            $table->unsignedSmallInteger('ord')->default(0);
            $table->timestamps();

            $table->unique(['book_id', 'number']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sections')->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('title');
            $table->string('slug');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedSmallInteger('page_printed_from')->nullable();
            $table->unsignedSmallInteger('page_printed_to')->nullable();
            $table->unsignedSmallInteger('page_pdf_from')->nullable();
            $table->unsignedSmallInteger('page_pdf_to')->nullable();
            $table->unsignedSmallInteger('ord')->default(0);
            $table->timestamps();

            $table->unique(['chapter_id', 'slug']);
            $table->index(['chapter_id', 'ord']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
        Schema::dropIfExists('chapters');
    }
};
