<?php

namespace App\Services\Content;

use App\Enums\AiActor;
use App\Enums\ContentStatus;
use App\Jobs\IndexContentJob;
use App\Models\CodeExample;
use App\Models\Concept;
use App\Models\ContentVersion;
use App\Models\Diagram;
use App\Models\Exercise;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The human gate for Pipeline A (docs/10: never auto-publish). Approving
 * snapshots the payload into content_versions with a diff summary, flips
 * status to published, records the reviewer and reindexes search.
 */
final class ContentApprover
{
    public const ENTITIES = [
        'lesson' => Lesson::class,
        'concept' => Concept::class,
        'exercise' => Exercise::class,
        'quiz_question' => QuizQuestion::class,
        'flashcard' => Flashcard::class,
        'diagram' => Diagram::class,
        'code_example' => CodeExample::class,
    ];

    /** Entities the search index knows about. */
    private const SEARCHABLE = [
        'lesson' => 'lesson',
        'concept' => 'concept',
        'exercise' => 'exercise',
        'flashcard' => 'flashcard',
        'code_example' => 'example',
    ];

    /**
     * Entities carrying the full provenance block (docs/06 "Applied to").
     * `concepts` is provenance-lite: source + status only, no review
     * columns - setting reviewed_by there would crash the UPDATE.
     */
    private const REVIEWABLE = [
        'lesson',
        'exercise',
        'quiz_question',
        'flashcard',
        'diagram',
        'code_example',
    ];

    public function __construct(private readonly Diff $diff) {}

    public function approve(string $entityType, int $entityId, User $admin): Model
    {
        $entity = $this->find($entityType, $entityId);

        $payload = $this->snapshot($entityType, $entity);

        $previous = ContentVersion::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest('id')
            ->first();

        ContentVersion::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'actor' => AiActor::Admin,
            'diff_summary' => $this->diff->summary($previous?->payload['lines'] ?? null, $payload['lines'] ?? []),
        ]);

        $entity->forceFill(array_merge(
            ['status' => ContentStatus::Published],
            $this->reviewFields($entityType, ['reviewed_by' => $admin->id, 'reviewed_at' => now()]),
        ))->save();

        if (isset(self::SEARCHABLE[$entityType])) {
            IndexContentJob::dispatch(self::SEARCHABLE[$entityType]);
        }

        return $entity;
    }

    public function reject(string $entityType, int $entityId): Model
    {
        $entity = $this->find($entityType, $entityId);

        $entity->forceFill(array_merge(
            ['status' => ContentStatus::Draft],
            $this->reviewFields($entityType, ['reviewed_by' => null, 'reviewed_at' => null]),
        ))->save();

        return $entity;
    }

    /**
     * Review columns only for entities whose table has them (docs/06).
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function reviewFields(string $entityType, array $fields): array
    {
        return in_array($entityType, self::REVIEWABLE, true) ? $fields : [];
    }

    /**
     * Stable line view of an entity payload for diffing: metadata lines
     * plus one line per block/row (payload['lines']).
     *
     * @return array<string, mixed>
     */
    public function snapshot(string $entityType, Model $entity): array
    {
        $lines = [];

        if ($entity instanceof Lesson) {
            $lines[] = 'title: '.$entity->title;
            $lines[] = 'summary: '.((string) $entity->summary);

            foreach ($entity->blocks as $block) {
                $text = $block->text();
                $json = json_encode($block->payload, JSON_UNESCAPED_UNICODE) ?: '';
                $lines[] = 'block '.$block->ord.' ('.$block->type->value.'): '
                    .mb_substr($text ?? $json, 0, 160);
            }

            return ['lines' => $lines, 'title' => $entity->title];
        }

        if ($entity instanceof Concept) {
            return [
                'lines' => ['name: '.$entity->name, 'slug: '.$entity->slug, 'definition: '.$entity->definition],
                'title' => $entity->name,
            ];
        }

        if ($entity instanceof Exercise) {
            return ['lines' => ['prompt: '.$entity->prompt], 'title' => mb_substr($entity->prompt, 0, 60)];
        }

        if ($entity instanceof QuizQuestion) {
            return ['lines' => ['stem: '.$entity->stem], 'title' => mb_substr($entity->stem, 0, 60)];
        }

        if ($entity instanceof Flashcard) {
            return ['lines' => ['front: '.$entity->front, 'back: '.$entity->back], 'title' => $entity->front];
        }

        if ($entity instanceof Diagram) {
            return ['lines' => ['title: '.$entity->title, 'mermaid: '.$entity->mermaid_source], 'title' => $entity->title];
        }

        if ($entity instanceof CodeExample) {
            return ['lines' => ['title: '.$entity->title, 'code: '.$entity->code], 'title' => $entity->title];
        }

        return ['lines' => $lines, 'title' => $entityType.' #'.$entity->getKey()];
    }

    private function find(string $entityType, int $entityId): Model
    {
        $class = self::ENTITIES[$entityType] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown entity type '{$entityType}'.");
        }

        return $class::query()->findOrFail($entityId);
    }
}
