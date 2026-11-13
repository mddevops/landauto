<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель.
 *
 * @property int $mark_id
 * @property int|null $external_id
 * @property string $name
 * @property string|null $name_ru
 * @property string $url
 * @property string|null $class
 * @property int|null $year_from
 * @property int|null $year_to
 * @property-read AutoMark $mark
 */
class AutoModel extends CatalogModel
{
    /** @use HasFactory<AutoModelFactory> */
    use HasFactory;

    protected $table = 'auto_models';

    protected $hidden = ['id', 'mark_id', 'parent_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year_from' => 'integer', 'year_to' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoMark, $this>
     */
    public function mark(): BelongsTo
    {
        return $this->belongsTo(AutoMark::class, 'mark_id');
    }

    /**
     * @return HasMany<AutoGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(AutoGeneration::class, 'model_id');
    }

    /**
     * @return HasMany<AutoSeries, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(AutoSeries::class, 'model_id');
    }
}
