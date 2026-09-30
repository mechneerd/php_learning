<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('card_type', 20)->default('definition');
            $table->text('front');
            $table->text('back');
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

            $table->index(['concept_id', 'card_type']);
            $table->index(['status', 'source']);
        });

        Schema::create('flashcard_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('flashcards')->cascadeOnDelete();
            $table->string('grade', 10);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('ease', 4, 2)->default(2.50);
            $table->timestamp('next_review_at');
            $table->timestamp('reviewed_at');

            $table->unique(['user_id', 'card_id']);
            $table->index(['user_id', 'next_review_at']);
        });

        Schema::create('recall_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->text('answer');
            $table->decimal('score', 5, 2)->default(0);
            $table->json('evaluation')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_attempts');
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcards');
    }
};
