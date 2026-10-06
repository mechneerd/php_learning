<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('skill_domain', 40);
            $table->string('level', 20)->default('unseen');
            $table->unsignedInteger('mastered_count')->default(0);
            $table->unsignedInteger('comfortable_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'skill_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_progress');
    }
};
