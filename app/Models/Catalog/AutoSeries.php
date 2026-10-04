<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Серия: the concrete body inside a generation (Седан, Универсал 5 дв.).
 *
 * @property int $generation_id
 * @property string $name
 * @property string $url
 * @property string|null $image
 * @property bool $status
 * @property int $sort_order
 * @property-read AutoGeneration $generation
 */
#[Fillable(['name', 'url', 'image', 'status', 'sort_order'])]
#[Hidden(['id', 'generation_id'])]
class AutoSeries extends CatalogModel
{
    /** @use HasFactory<AutoSeriesFactory> */
    use HasFactory;

    protected const PARENT_KEY = 'generation_id';

    protected $table = 'auto_series';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoGeneration, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AutoGeneration::class, 'generation_id');
    }

    /**
     * @return HasMany<AutoModification, $this>
     */
    public function modifications(): HasMany
    {
        return $this->hasMany(AutoModification::class, 'series_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true)
            ->whereHas('generation', fn (Builder $generation) => $generation->available());
    }
}
