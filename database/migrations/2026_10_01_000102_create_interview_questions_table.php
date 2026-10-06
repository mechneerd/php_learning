<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 40);
            $table->text('question');
            $table->text('model_answer');
            $table->json('follow_ups')->nullable();
            $table->string('difficulty', 10)->default('medium');
            $table->unsignedSmallInteger('ord')->default(0);

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

            $table->index(['topic', 'status']);
            $table->index(['status', 'ord']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_questions');
    }
};
