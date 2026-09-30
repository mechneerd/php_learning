<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdf_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_pdf');
            $table->unsignedInteger('page_printed')->nullable();
            $table->longText('text')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->decimal('extraction_quality', 4, 2)->default(0);
            $table->boolean('needs_review')->default(false);
            $table->timestamps();

            $table->unique(['pdf_document_id', 'page_pdf']);
            $table->index(['pdf_document_id', 'page_printed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_pages');
    }
};
