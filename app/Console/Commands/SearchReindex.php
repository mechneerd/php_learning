<?php

namespace App\Console\Commands;

use App\Services\Content\SearchIndexer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SearchReindex extends Command
{
    protected $signature = 'search:reindex {--entity= : One of lesson, concept, example, exercise, flashcard} {--all : Reindex every entity type}';

    protected $description = 'Rebuild the search index (FTS5 with LIKE fallback)';

    public function handle(SearchIndexer $indexer): int
    {
        if ($this->option('all')) {
            $count = $indexer->indexAll();
            $this->info("Indexed {$count} rows across all entities.");

            return self::SUCCESS;
        }

        $entity = $this->option('entity');

        if ($entity === null) {
            $this->error('Pass --entity=<type> or --all.');

            return self::FAILURE;
        }

        try {
            $count = $indexer->indexEntity((string) $entity);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Indexed {$count} rows for [{$entity}].");

        return self::SUCCESS;
    }
}
