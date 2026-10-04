<?php

namespace App\Models\Catalog;

use App\Catalog\CatalogDatabase;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Global Automotive Catalog row on the separate catalog connection (D-102). Platform-owned;
 * browser contracts use `public_id` only.
 *
 * @property int $id
 * @property string $public_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class CatalogModel extends Model
{
    use HasImmutablePublicId;

    protected $connection = CatalogDatabase::CONNECTION;

    /**
     * Hierarchy foreign key that is fixed after creation, so catalog references held by
     * customer Sites never silently move to another branch.
     */
    protected const PARENT_KEY = null;

    protected static function booted(): void
    {
        static::updating(function (CatalogModel $model): void {
            $key = $model::PARENT_KEY;

            if ($key !== null && $model->isDirty($key)) {
                throw new InvalidCatalogDataException('Catalog hierarchy parent cannot be changed after creation.');
            }
        });

        static::saving(function (CatalogModel $model): void {
            $from = $model->getAttribute('year_from');
            $to = $model->getAttribute('year_to');

            if ($from !== null && $to !== null && (int) $to < (int) $from) {
                throw new InvalidCatalogDataException('year_to must not be earlier than year_from.');
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }
}
