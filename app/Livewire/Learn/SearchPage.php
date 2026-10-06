<?php

namespace App\Livewire\Learn;

use App\Services\Content\SearchIndexer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Search (docs/08 screen 13): grouped results with snippets, backed by
 * the FTS5 index (search:reindex).
 */
#[Title('Search')]
class SearchPage extends Component
{
    #[Url]
    public string $q = '';

    public function render(SearchIndexer $indexer): View
    {
        $term = trim($this->q);

        return view('livewire.learn.search', [
            'term' => $term,
            'results' => $term === '' ? null : $indexer->search($term),
            'groups' => array_map(
                static fn (string $type): string => ucfirst($type).'s',
                SearchIndexer::ALL,
            ),
        ]);
    }
}
