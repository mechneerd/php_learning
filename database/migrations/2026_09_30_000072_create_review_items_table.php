<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40);
            $table->timestamp('due_at');
            $table->string('status', 10)->default('due');
            $table->timestamps();

            $table->unique(['user_id', 'item_type', 'item_id']);
            $table->index(['user_id', 'due_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
