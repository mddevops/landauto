<?php

namespace App\Models\Catalog;

use App\Exceptions\InvalidCatalogDataException;
use Database\Factories\Catalog\AutoModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель. `parent_id` is an optional grouping of models inside the same Mark, not a generation.
 *
 * @property int $mark_id
 * @property string $name
 * @property string|null $name_ru
 * @property string $url
 * @property string|null $class
 * @property int|null $year_from
 * @property int|null $year_to
 * @property int|null $parent_id
 * @property bool $status
 * @property int $sort_order
 * @property-read AutoMark $mark
 * @property-read AutoModel|null $parent
 */
#[Fillable(['name', 'name_ru', 'url', 'class', 'year_from', 'year_to', 'status', 'sort_order'])]
#[Hidden(['id', 'mark_id', 'parent_id'])]
class AutoModel extends CatalogModel
{
    /** @use HasFactory<AutoModelFactory> */
    use HasFactory;

    protected const PARENT_KEY = 'mark_id';

    protected $table = 'auto_models';

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

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (AutoModel $model): void {
            if ($model->parent_id === null) {
                return;
            }

            if ($model->exists && $model->parent_id === $model->id) {
                throw new InvalidCatalogDataException('A model cannot be its own parent.');
            }

            $seen = $model->exists ? [$model->id] : [];
            $parent = AutoModel::query()->find($model->parent_id);

            if ($parent === null || $parent->mark_id !== $model->mark_id) {
                throw new InvalidCatalogDataException('A model parent must belong to the same mark.');
            }

            while ($parent !== null) {
                if (in_array($parent->id, $seen, true)) {
                    throw new InvalidCatalogDataException('Model grouping must not contain cycles.');
                }

                $seen[] = $parent->id;
                $parent = $parent->parent_id === null ? null : AutoModel::query()->find($parent->parent_id);
            }
        });
    }

    /**
     * @return BelongsTo<AutoMark, $this>
     */
    public function mark(): BelongsTo
    {
        return $this->belongsTo(AutoMark::class, 'mark_id');
    }

    /**
     * @return BelongsTo<AutoModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutoModel::class, 'parent_id');
    }

    /**
     * @return HasMany<AutoGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(AutoGeneration::class, 'model_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true)
            ->whereHas('mark', fn (Builder $mark) => $mark->where('status', true));
    }
}
