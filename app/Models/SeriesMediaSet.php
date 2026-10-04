<?php

namespace App\Models;

use App\Catalog\CatalogReferences;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SeriesMediaSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform-owned prepared visual variant of a catalog Series (usually a customer-visible color).
 * The swatch is display metadata only; it is not an automotive color domain (D-103).
 *
 * @property int $id
 * @property string $public_id
 * @property string $catalog_series_public_id
 * @property string $name
 * @property string|null $swatch_hex
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'swatch_hex', 'status', 'sort_order'])]
#[Hidden(['id'])]
class SeriesMediaSet extends Model
{
    /** @use HasFactory<SeriesMediaSetFactory> */
    use HasFactory, HasImmutablePublicId;

    public const SWATCH_PATTERN = '/^#[0-9a-f]{6}$/';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (SeriesMediaSet $set): void {
            if ($set->exists && $set->isDirty('catalog_series_public_id')) {
                throw new InvalidCatalogDataException('A media set cannot move to another Series.');
            }

            if (! $set->exists && app(CatalogReferences::class)->series($set->catalog_series_public_id) === null) {
                throw new InvalidCatalogDataException('Media set Series does not exist in the catalog.');
            }

            if ($set->swatch_hex !== null && preg_match(self::SWATCH_PATTERN, $set->swatch_hex) !== 1) {
                throw new InvalidCatalogDataException('Swatch must be a lowercase #rrggbb color.');
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
