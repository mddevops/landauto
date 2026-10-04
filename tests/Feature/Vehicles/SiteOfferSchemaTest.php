<?php

namespace Tests\Feature\Vehicles;

use App\Enums\BenefitType;
use App\Enums\OfferAvailability;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteOfferSchemaTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_offer_stores_integer_money_for_an_equipment_of_the_vehicle_series(): void
    {
        $this->assertTrue(Schema::hasColumns('site_offers', ['price_minor', 'rrp_minor', 'currency', 'catalog_equipment_public_id']));
        $this->assertFalse(Schema::hasColumn('site_offers', 'price'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('site_offers'));

        $series = AutoSeries::factory()->create();
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        $comfort = $this->equipmentOf($series, 'Comfort');
        $prestige = $this->equipmentOf($series, 'Prestige');

        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($comfort)->create([
            'price_minor' => 185_000_000,
            'rrp_minor' => 199_000_000,
            'availability' => OfferAvailability::InStock,
            'badge' => 'Хит',
        ])->fresh();

        $this->assertNotNull($offer);
        $this->assertSame([185_000_000, 199_000_000, 'RUB'], [$offer->price_minor, $offer->rrp_minor, $offer->currency]);
        $this->assertSame(OfferAvailability::InStock, $offer->availability);
        $this->assertArrayNotHasKey('site_vehicle_id', $offer->toArray());

        $offer->update(['catalog_equipment_public_id' => $prestige->public_id]);
        $this->assertSame($prestige->public_id, $offer->fresh()?->catalog_equipment_public_id);
        $this->assertSame(1, $vehicle->offers()->count());
    }

    public function test_equipment_from_another_series_or_unknown_equipment_is_rejected(): void
    {
        $vehicle = SiteVehicle::factory()->create();
        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->create();
        $foreign = AutoEquipment::factory()->create();
        $rejected = 0;

        foreach ([
            fn () => SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($foreign)->create(),
            fn () => SiteOffer::factory()->for($vehicle, 'vehicle')->create(['catalog_equipment_public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz']),
            fn () => $offer->update(['catalog_equipment_public_id' => $foreign->public_id]),
            fn () => SiteOffer::factory()->for($vehicle, 'vehicle')->create(['currency' => 'USD']),
            fn () => SiteOffer::factory()->for($vehicle, 'vehicle')->create(['price_minor' => -1]),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(5, $rejected);
        $this->assertSame(1, SiteOffer::query()->count());

        $this->expectException(LogicException::class);
        $offer->refresh()->forceFill(['site_vehicle_id' => SiteVehicle::factory()->create()->id])->save();
    }

    public function test_benefits_are_amount_based_and_replaced_in_order(): void
    {
        $offer = SiteOffer::factory()->create();

        $offer->replaceBenefits([
            ['type' => BenefitType::TradeIn, 'amount_minor' => 10_000_000],
            ['type' => BenefitType::Discount, 'amount_minor' => 5_000_000, 'label' => 'Сезонная скидка'],
        ]);
        $this->assertSame(['trade_in', 'discount'], $offer->benefits()->get()->map(fn ($benefit) => $benefit->type->value)->all());
        $this->assertSame([10_000_000, 5_000_000], $offer->benefits()->pluck('amount_minor')->all());

        try {
            $offer->replaceBenefits([
                ['type' => BenefitType::Credit, 'amount_minor' => 1_000_000],
                ['type' => BenefitType::Discount, 'amount_minor' => 0],
            ]);
            $this->fail('Zero benefit must be rejected.');
        } catch (InvalidCatalogDataException) {
            $this->assertSame(['trade_in', 'discount'], $offer->benefits()->get()->map(fn ($benefit) => $benefit->type->value)->all());
        }
    }

    private function equipmentOf(AutoSeries $series, string $name): AutoEquipment
    {
        return AutoEquipment::factory()
            ->for(AutoModification::factory()->for($series, 'series'), 'modification')
            ->create(['name' => $name]);
    }
}
