<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('sha256', 64)->unique();
            $table->unsignedInteger('page_count')->default(0);
            $table->string('status', 20)->default('uploaded');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->foreign('source_pdf_document_id')
                ->references('id')
                ->on('pdf_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropForeign(['source_pdf_document_id']);
        });

        Schema::dropIfExists('pdf_documents');
    }
};
