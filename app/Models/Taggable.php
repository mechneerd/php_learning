<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Polymorphic pivot between tags and content rows.
 *
 * @property int $id
 * @property int $tag_id
 * @property string $taggable_type
 * @property int $taggable_id
 * @property-read Tag $tag
 */
#[Fillable(['tag_id', 'taggable_type', 'taggable_id'])]
class Taggable extends MorphPivot
{
    public $incrementing = true;

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
