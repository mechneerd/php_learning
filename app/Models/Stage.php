<?php

namespace App\Models;

use App\Enums\StageSource;
use Database\Factories\StageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $number
 * @property string $slug
 * @property string $name
 * @property string|null $subtitle
 * @property StageSource $source
 * @property string|null $description
 * @property array<int, array<string, mixed>>|null $gate_rules
 * @property int $ord
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Lesson> $lessons
 */
#[Fillable(['number', 'slug', 'name', 'subtitle', 'source', 'description', 'gate_rules', 'ord'])]
class Stage extends Model
{
    /** @use HasFactory<StageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'source' => StageSource::class,
            'gate_rules' => 'array',
            'ord' => 'integer',
        ];
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('ord');
    }
}
