<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdf_document_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('queued');
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['pdf_document_id', 'status']);
        });

        Schema::create('page_review_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdf_page_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_review_flags');
        Schema::dropIfExists('import_jobs');
    }
};
