<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('definition');
            $table->string('skill_domain', 40);
            $table->string('granularity', 20)->default('concept');
            $table->boolean('is_core')->default(false);
            $table->string('source', 20)->default('ai');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['skill_domain']);
            $table->index(['status', 'source']);
        });

        Schema::create('lesson_concepts', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('core');

            $table->primary(['lesson_id', 'concept_id']);
            $table->index(['concept_id', 'role']);
        });

        Schema::create('concept_prerequisites', function (Blueprint $table): void {
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prereq_concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->unique(['concept_id', 'prereq_concept_id']);
            $table->index(['prereq_concept_id']);
        });

        Schema::table('code_examples', function (Blueprint $table): void {
            $table->unsignedBigInteger('concept_id')->nullable()->change();
        });

        Schema::table('code_examples', function (Blueprint $table): void {
            $table->foreign('concept_id')->references('id')->on('concepts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('code_examples', function (Blueprint $table): void {
            $table->dropForeign(['concept_id']);
        });

        Schema::dropIfExists('concept_prerequisites');
        Schema::dropIfExists('lesson_concepts');
        Schema::dropIfExists('concepts');
    }
};
