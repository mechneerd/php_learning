<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Models\CodeExample;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\Flashcard;
use App\Models\Lesson;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the search_index table (docs/06 module I) and answers grouped
 * searches (docs/08 screen 13). Uses FTS5 MATCH when the virtual table was
 * created, with a LIKE fallback for builds without FTS5.
 */
final class SearchIndexer
{
    public const LESSON = 'lesson';

    public const CONCEPT = 'concept';

    public const EXAMPLE = 'example';

    public const EXERCISE = 'exercise';

    public const FLASHCARD = 'flashcard';

    /** @var list<string> */
    public const ALL = [self::LESSON, self::CONCEPT, self::EXAMPLE, self::EXERCISE, self::FLASHCARD];

    /**
     * Rebuild one entity type (or every type) from scratch.
     */
    public function indexAll(?string $entity = null): int
    {
        $types = $entity === null ? self::ALL : [$entity];

        $count = 0;
        foreach ($types as $type) {
            $count += $this->indexEntity($type);
        }

        return $count;
    }

    public function indexEntity(string $entity): int
    {
        if (! in_array($entity, self::ALL, true)) {
            throw new \InvalidArgumentException("Unknown search entity [{$entity}].");
        }

        $this->clear($entity);

        $count = 0;

        foreach ($this->rowsFor($entity) as $row) {
            DB::table('search_index')->insert($row);
            $count++;
        }

        return $count;
    }

    public function clear(?string $entity = null): void
    {
        $query = DB::table('search_index');

        if ($entity !== null) {
            $query->where('entity_type', $entity);
        }

        $query->delete();
    }

    public function isFts(): bool
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return false;
        }

        $rows = DB::select("SELECT sql FROM sqlite_master WHERE name = 'search_index'");

        return isset($rows[0]) && str_contains((string) ($rows[0]->sql ?? ''), 'fts5');
    }

    /**
     * Grouped results with snippet and URL, keyed by entity type in
     * display order (docs/08 screen 13).
     *
     * @return array<string, list<array{entity_id: int, title: string, snippet: string, url: string|null}>>
     */
    public function search(string $term, int $perGroup = 5): array
    {
        $tokens = $this->tokens($term);

        $empty = [];
        foreach (self::ALL as $type) {
            $empty[$type] = [];
        }

        if ($tokens === []) {
            return $empty;
        }

        $grouped = [];

        foreach (self::ALL as $type) {
            $grouped[$type] = [];

            foreach ($this->queryRows($tokens, $perGroup, $type) as $row) {
                $grouped[$type][] = [
                    'entity_id' => (int) $row->entity_id,
                    'title' => (string) $row->title,
                    'snippet' => $this->snippet((string) $row->body, $tokens),
                ];
            }
        }

        $urls = $this->urlsByType($grouped);

        $result = [];
        foreach (self::ALL as $type) {
            $items = [];
            foreach ($grouped[$type] as $item) {
                $item['url'] = $urls[$type][$item['entity_id']] ?? null;
                $items[] = $item;
            }
            $result[$type] = $items;
        }

        return $result;
    }

    /**
     * @return list<array{entity_type: string, entity_id: int, title: string, body: string}>
     */
    private function rowsFor(string $entity): array
    {
        $rows = [];

        if ($entity === self::LESSON) {
            $this->lessonRows($rows);
        } elseif ($entity === self::CONCEPT) {
            $this->conceptRows($rows);
        } elseif ($entity === self::EXAMPLE) {
            $this->exampleRows($rows);
        } elseif ($entity === self::EXERCISE) {
            $this->exerciseRows($rows);
        } elseif ($entity === self::FLASHCARD) {
            $this->flashcardRows($rows);
        } else {
            throw new \InvalidArgumentException("Unknown search entity [{$entity}].");
        }

        return $rows;
    }

    /** @param list<array{entity_type: string, entity_id: int, title: string, body: string}> $rows */
    private function lessonRows(array &$rows): void
    {
        foreach (Lesson::query()->published()->get(['id', 'title', 'summary']) as $lesson) {
            $body = (string) $lesson->summary;

            foreach ($lesson->blocks()->get(['payload']) as $block) {
                $body .= ' '.$this->flatten($block->payload);
            }

            $rows[] = [
                'entity_type' => self::LESSON,
                'entity_id' => $lesson->id,
                'title' => (string) $lesson->title,
                'body' => trim($body),
            ];
        }
    }

    /** @param list<array{entity_type: string, entity_id: int, title: string, body: string}> $rows */
    private function conceptRows(array &$rows): void
    {
        foreach (Concept::query()->where('status', 'published')->get(['id', 'name', 'definition']) as $concept) {
            $rows[] = [
                'entity_type' => self::CONCEPT,
                'entity_id' => $concept->id,
                'title' => (string) $concept->name,
                'body' => (string) $concept->definition,
            ];
        }
    }

    /** @param list<array{entity_type: string, entity_id: int, title: string, body: string}> $rows */
    private function exampleRows(array &$rows): void
    {
        foreach (CodeExample::query()->where('status', ContentStatus::Published)->get(['id', 'title', 'code', 'explanation']) as $example) {
            $rows[] = [
                'entity_type' => self::EXAMPLE,
                'entity_id' => $example->id,
                'title' => (string) $example->title,
                'body' => trim((string) $example->code.' '.(string) $example->explanation),
            ];
        }
    }

    /** @param list<array{entity_type: string, entity_id: int, title: string, body: string}> $rows */
    private function exerciseRows(array &$rows): void
    {
        foreach (Exercise::query()->published()->get(['id', 'prompt', 'explanation']) as $exercise) {
            $rows[] = [
                'entity_type' => self::EXERCISE,
                'entity_id' => $exercise->id,
                'title' => mb_substr((string) $exercise->prompt, 0, 80),
                'body' => trim((string) $exercise->prompt.' '.(string) $exercise->explanation),
            ];
        }
    }

    /** @param list<array{entity_type: string, entity_id: int, title: string, body: string}> $rows */
    private function flashcardRows(array &$rows): void
    {
        foreach (Flashcard::query()->published()->get(['id', 'front', 'back']) as $card) {
            $rows[] = [
                'entity_type' => self::FLASHCARD,
                'entity_id' => $card->id,
                'title' => (string) $card->front,
                'body' => trim((string) $card->front.' '.(string) $card->back),
            ];
        }
    }

    /**
     * @param  list<string>  $tokens
     * @return array<int, \stdClass>
     */
    private function queryRows(array $tokens, int $limit, string $entityType): array
    {
        if ($this->isFts()) {
            $match = implode(' AND ', array_map(fn (string $token): string => '"'.$token.'"*', $tokens));

            try {
                return DB::select(
                    'SELECT entity_type, entity_id, title, body FROM search_index WHERE search_index MATCH ? AND entity_type = ? ORDER BY rank LIMIT '.$limit,
                    [$match, $entityType],
                );
            } catch (\Throwable) {
                // Fall through to LIKE when the match expression is rejected.
            }
        }

        $query = DB::table('search_index')->where('entity_type', $entityType);

        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where(
                fn (Builder $builder): Builder => $builder
                    ->where('title', 'LIKE', $like)
                    ->orWhere('body', 'LIKE', $like),
            );
        }

        return $query->limit($limit)->get(['entity_type', 'entity_id', 'title', 'body'])->all();
    }

    /**
     * @param  list<string>  $tokens
     */
    private function snippet(string $body, array $tokens): string
    {
        $position = null;

        foreach ($tokens as $token) {
            $found = mb_stripos($body, $token);

            if ($found !== false) {
                $position = $found;
                break;
            }
        }

        $start = $position === null
            ? 0
            : max(0, $position - 60);
        $excerpt = mb_substr($body, $start, 140);
        $escaped = e($excerpt);

        if ($position !== null && $start > 0) {
            $escaped = '&hellip;'.$escaped;
        }

        if ($start + 140 < mb_strlen($body)) {
            $escaped .= '&hellip;';
        }

        $pattern = '/('.implode('|', array_map(fn (string $t): string => preg_quote($t, '/'), $tokens)).')/iu';

        return (string) preg_replace($pattern, '<mark>$1</mark>', $escaped);
    }

    /**
     * @param  array<string, list<array{entity_id: int, title: string, snippet: string}>>  $grouped
     * @return array<string, array<int, string|null>>
     */
    private function urlsByType(array $grouped): array
    {
        $urls = [];

        $lessonSlugs = Lesson::query()
            ->whereIn('id', $this->ids($grouped, self::LESSON))
            ->pluck('slug', 'id');
        foreach ($lessonSlugs as $id => $slug) {
            $urls[self::LESSON][(int) $id] = route('lessons.show', $slug);
        }

        $conceptSlugs = Concept::query()
            ->whereIn('id', $this->ids($grouped, self::CONCEPT))
            ->pluck('slug', 'id');
        foreach ($conceptSlugs as $id => $slug) {
            $urls[self::CONCEPT][(int) $id] = route('concepts.show', $slug);
        }

        $exampleLessons = CodeExample::query()
            ->with('lesson:id,slug')
            ->whereIn('id', $this->ids($grouped, self::EXAMPLE))
            ->get(['id', 'lesson_id']);
        foreach ($exampleLessons as $example) {
            $urls[self::EXAMPLE][$example->id] = route('lessons.show', $example->lesson->slug);
        }

        $exerciseLessons = Exercise::query()
            ->whereIn('id', $this->ids($grouped, self::EXERCISE))
            ->pluck('lesson_id', 'id');
        $practiceSlugs = Lesson::query()
            ->whereIn('id', $exerciseLessons->values())
            ->pluck('slug', 'id');
        foreach ($exerciseLessons as $exerciseId => $lessonId) {
            $slug = $practiceSlugs[$lessonId] ?? null;
            $urls[self::EXERCISE][(int) $exerciseId] = $slug !== null
                ? route('practice.show', $slug)
                : null;
        }

        foreach ($grouped[self::FLASHCARD] ?? [] as $item) {
            $urls[self::FLASHCARD][$item['entity_id']] = route('flashcards');
        }

        return $urls;
    }

    /**
     * @param  array<string, list<array{entity_id: int, title: string, snippet: string}>>  $grouped
     * @return list<int>
     */
    private function ids(array $grouped, string $type): array
    {
        return array_map(
            fn (array $item): int => $item['entity_id'],
            $grouped[$type] ?? [],
        );
    }

    /**
     * @return list<string>
     */
    private function tokens(string $term): array
    {
        preg_match_all('/[\p{L}\p{N}_]+/u', $term, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Recursively collect strings from a payload array.
     *
     * @param  array<mixed>  $payload
     */
    private function flatten(array $payload): string
    {
        $parts = [];

        foreach ($payload as $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_array($value)) {
                $parts[] = $this->flatten($value);
            }
        }

        return implode(' ', $parts);
    }
}
