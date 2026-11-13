<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Серия (кузов) of a generation. Customer rows reference it by {@see ref()}.
 *
 * @property int $model_id
 * @property int $generation_id
 * @property int|null $external_id
 * @property string $name
 * @property string $url
 * @property string|null $image
 * @property-read AutoGeneration $generation
 * @property-read AutoModel $model
 */
class AutoSeries extends CatalogModel
{
    /** @use HasFactory<AutoSeriesFactory> */
    use HasFactory;

    protected $table = 'auto_series';

    protected $hidden = ['id', 'model_id', 'generation_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['external_id' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoGeneration, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AutoGeneration::class, 'generation_id');
    }

    /**
     * @return BelongsTo<AutoModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AutoModel::class, 'model_id');
    }

    /**
     * @return HasMany<AutoModification, $this>
     */
    public function modifications(): HasMany
    {
        return $this->hasMany(AutoModification::class, 'series_id');
    }

    /**
     * @return HasMany<AutoEquipment, $this>
     */
    public function equipments(): HasMany
    {
        return $this->hasMany(AutoEquipment::class, 'series_id');
    }

    /**
     * Stable cross-environment reference `{generation external id}:{series external id}`.
     */
    public function ref(): ?string
    {
        $generation = $this->generation->external_id;

        return $generation === null || $this->external_id === null ? null : "{$generation}:{$this->external_id}";
    }
}
