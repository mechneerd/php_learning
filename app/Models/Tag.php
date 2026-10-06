<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A learner-created tag applied to content rows via taggables.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read HasMany<Taggable, $this> $taggables
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** @return HasMany<Taggable, $this> */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }

    public static function fromName(string $name): Tag
    {
        return static::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );
    }
}
