<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16)->default('tutor');
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lesson_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('stage', 32);
            $table->string('prompt_version', 16);
            $table->string('model', 64);
            $table->string('status', 16)->default('queued');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->text('error')->nullable();
            $table->string('input_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['stage', 'prompt_version', 'input_hash']);
        });

        Schema::create('content_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('payload');
            $table->string('actor', 16);
            $table->text('diff_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
