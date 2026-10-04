<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Поколение. A restyling is its own generation row.
 *
 * @property int $model_id
 * @property string $name
 * @property string $url
 * @property int|null $year_from
 * @property int|null $year_to
 * @property bool $status
 * @property int $sort_order
 * @property-read AutoModel $model
 */
#[Fillable(['name', 'url', 'year_from', 'year_to', 'status', 'sort_order'])]
#[Hidden(['id', 'model_id'])]
class AutoGeneration extends CatalogModel
{
    /** @use HasFactory<AutoGenerationFactory> */
    use HasFactory;

    protected const PARENT_KEY = 'model_id';

    protected $table = 'auto_generations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
            'year_from' => 'integer',
            'year_to' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AutoModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AutoModel::class, 'model_id');
    }

    /**
     * @return HasMany<AutoSeries, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(AutoSeries::class, 'generation_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true)
            ->whereHas('model', fn (Builder $model) => $model->available());
    }
}
