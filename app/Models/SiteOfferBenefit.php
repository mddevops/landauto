<?php

namespace App\Models;

use App\Enums\BenefitType;
use App\Exceptions\InvalidCatalogDataException;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Amount-based benefit in the parent offer's currency (ADR-004).
 *
 * @property int $id
 * @property int $site_offer_id
 * @property BenefitType $type
 * @property int $amount_minor
 * @property string|null $label
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'amount_minor', 'label', 'sort_order'])]
#[Hidden(['id', 'site_offer_id'])]
class SiteOfferBenefit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => BenefitType::class, 'amount_minor' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteOfferBenefit $benefit): void {
            if ($benefit->amount_minor < 1 || $benefit->amount_minor > Money::MAX_MINOR) {
                throw new InvalidCatalogDataException('Benefit amount is out of range.');
            }
        });
    }

    /**
     * @return BelongsTo<SiteOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(SiteOffer::class, 'site_offer_id');
    }
}
