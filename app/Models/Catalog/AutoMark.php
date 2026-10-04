<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Марка.
 *
 * @property string $name
 * @property string|null $name_ru
 * @property string $url
 * @property string|null $logo_min
 * @property string|null $logo_big
 * @property string|null $country
 * @property bool $status
 * @property int $sort_order
 */
#[Fillable(['name', 'name_ru', 'url', 'logo_min', 'logo_big', 'country', 'status', 'sort_order'])]
#[Hidden(['id'])]
class AutoMark extends CatalogModel
{
    /** @use HasFactory<AutoMarkFactory> */
    use HasFactory;

    protected $table = 'auto_marks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return HasMany<AutoModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AutoModel::class, 'mark_id');
    }

    /**
     * Visible in customer selection: active itself and through the whole parent chain.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true);
    }
}
