<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('difficulty', 10)->default('medium');
            $table->text('prompt');
            $table->text('starter_code')->nullable();
            $table->text('solution_code')->nullable();
            $table->text('explanation')->nullable();
            $table->json('expected_answer')->nullable();
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

            $table->index(['lesson_id', 'ord']);
            $table->index(['status', 'source']);
        });

        Schema::create('exercise_hints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->text('text');
            $table->timestamps();

            $table->unique(['exercise_id', 'level']);
        });

        Schema::create('exercise_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ord');
            $table->string('type', 20);
            $table->json('payload');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();

            $table->index(['exercise_id', 'ord']);
        });

        Schema::create('exercise_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->text('code');
            $table->string('result', 20);
            $table->unsignedSmallInteger('hints_used')->default(0);
            $table->unsignedInteger('duration_sec')->default(0);
            $table->json('test_results')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'exercise_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('exercise_tests');
        Schema::dropIfExists('exercise_hints');
        Schema::dropIfExists('exercises');
    }
};
