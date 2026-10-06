<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20)->default('beginner');
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('brief');
            $table->json('requirements');
            $table->text('solution_ref')->nullable();
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

            $table->index(['level', 'ord']);
            $table->index(['status', 'source']);
        });

        Schema::create('project_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ord');
            $table->text('brief');
            $table->json('concept_ids')->nullable();
            $table->text('solution_hint')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'ord']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
    }
};
