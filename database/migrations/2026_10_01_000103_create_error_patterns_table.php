<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_patterns', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name', 120);
            $table->string('category', 20);
            $table->text('symptom');
            $table->text('cause');
            $table->json('identify_steps');
            $table->json('fix_steps');
            $table->json('prevent_steps');
            $table->text('practice_ref')->nullable();

            // Provenance block
            $table->string('source', 20)->default('ai');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('page_printed_from')->nullable();
            $table->unsignedSmallInteger('page_printed_to')->nullable();
            $table->unsignedSmallInteger('page_pdf_from')->nullable();
            $table->unsignedSmallInteger('page_pdf_to')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('ai_prompt_version')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_outdated')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_patterns');
    }
};
