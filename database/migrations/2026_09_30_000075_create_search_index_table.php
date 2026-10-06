<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite FTS5 virtual table (docs/06 module I) with a plain-table fallback
 * for builds without FTS5; SearchIndexer detects which shape it got.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement(
                'CREATE VIRTUAL TABLE "search_index" USING fts5(entity_type UNINDEXED, entity_id UNINDEXED, title, body)',
            );
        } catch (Throwable) {
            Schema::create('search_index', function (Blueprint $table): void {
                $table->id();
                $table->string('entity_type', 30);
                $table->unsignedBigInteger('entity_id');
                $table->string('title');
                $table->text('body');

                $table->unique(['entity_type', 'entity_id']);
                $table->index('entity_type');
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP TABLE IF EXISTS "search_index"');
        } catch (Throwable) {
            Schema::dropIfExists('search_index');
        }
    }
};
