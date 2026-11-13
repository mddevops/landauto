<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoMarkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Марка.
 *
 * @property int|null $external_id
 * @property string $name
 * @property string|null $name_ru
 * @property string $url
 * @property string|null $logo_min
 * @property string|null $logo_big
 * @property string|null $logo_dark
 * @property string|null $country
 * @property int|null $popular
 * @property int|null $ordering
 */
class AutoMark extends CatalogModel
{
    /** @use HasFactory<AutoMarkFactory> */
    use HasFactory;

    protected $table = 'auto_marks';

    protected $hidden = ['id'];

    /**
     * @return HasMany<AutoModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AutoModel::class, 'mark_id');
    }

    /**
     * Popular marks first (catalog `ordering`), then alphabetically.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('ordering is null')->orderBy('ordering')->orderBy('name');
    }
}
