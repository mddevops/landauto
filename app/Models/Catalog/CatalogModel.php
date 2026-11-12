<?php

namespace App\Models\Catalog;

use App\Catalog\CatalogDatabase;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Read-only row of the auto.ru catalog on the separate `catalog` connection (ADR-010). The
 * catalog team owns the data; Landflow writes only in tests and local demo seeding. Numeric
 * ids differ between environments and never leave the server.
 *
 * @property int $id
 */
abstract class CatalogModel extends Model
{
    public $timestamps = false;

    protected $connection = CatalogDatabase::CONNECTION;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (): void {
            if (app()->isProduction()) {
                throw new LogicException('The auto.ru catalog is read-only.');
            }
        });
    }
}
