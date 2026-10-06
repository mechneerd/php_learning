<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The diagrams table shipped without the provenance block (docs/06
 * "Applied to" lists diagrams); code_examples in the same migration got
 * it. Without reviewed_by, approving a diagram in the review queue
 * crashes the UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table): void {
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->string('ai_prompt_version')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_outdated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['ai_model', 'ai_generated_at', 'ai_prompt_version', 'reviewed_at', 'is_outdated']);
        });
    }
};
