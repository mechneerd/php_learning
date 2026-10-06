<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('noteable_type');
            $table->unsignedBigInteger('noteable_id');
            $table->string('kind', 20)->default('note');
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'noteable_type', 'noteable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
