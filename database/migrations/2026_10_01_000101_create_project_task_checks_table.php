<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'project_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_checks');
    }
};
