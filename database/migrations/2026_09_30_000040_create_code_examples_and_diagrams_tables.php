<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // FK to `concepts` is added in Phase 3, when that table exists.
            $table->unsignedInteger('concept_id')->nullable();
            $table->string('listing_ref', 20)->nullable();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->string('title');
            $table->text('code');
            $table->text('expected_output')->nullable();
            $table->text('explanation')->nullable();
            $table->text('syntax_notes')->nullable();
            $table->text('common_mistake')->nullable();
            $table->string('external_ref')->nullable();
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

            $table->timestamps();

            $table->index(['lesson_id', 'ord']);
            $table->index(['lesson_id', 'status']);
            $table->index('status');
        });

        Schema::create('diagrams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('flowchart');
            $table->string('title');
            $table->text('mermaid_source');
            $table->string('source', 20)->default('ai');
            $table->string('figure_ref', 20)->nullable();
            $table->unsignedSmallInteger('page_pdf')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['lesson_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagrams');
        Schema::dropIfExists('code_examples');
    }
};
