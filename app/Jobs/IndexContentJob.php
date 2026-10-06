<?php

namespace App\Jobs;

use App\Services\Content\SearchIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline A: push a published entity type into the search index
 * (docs/10 IndexContentJob; also dispatched on approve).
 */
class IndexContentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $entity) {}

    public function handle(SearchIndexer $indexer): void
    {
        $indexer->indexEntity($this->entity);
    }
}
