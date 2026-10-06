<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->uuid('attempt_id');
            $table->text('code');
            $table->string('status')->default('queued');
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->integer('exit_code')->nullable();
            $table->json('metrics')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'created_at']);
            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_runs');
    }
};
