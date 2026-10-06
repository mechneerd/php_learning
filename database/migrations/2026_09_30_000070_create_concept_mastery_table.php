<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_mastery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20)->default('unseen');
            $table->json('evidence')->nullable();
            $table->timestamp('mastered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'concept_id']);
            $table->index(['user_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_mastery');
    }
};
