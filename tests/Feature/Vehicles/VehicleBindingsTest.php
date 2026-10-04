<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleBindings;
use App\Blocks\BlockStateValidator;
use App\Blocks\PageBlockReferences;
use App\Catalog\EquipmentCharacteristics;
use App\Catalog\EquipmentOptions;
use App\Enums\BenefitType;
use App\Enums\Catalog\OptionAvailability;
use App\Enums\MediaAngle;
use App\Enums\OfferAvailability;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoOption;
use App\Models\Catalog\AutoSeries;
use App\Models\Page;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VehicleBindingsTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Site $site;

    private AutoSeries $series;

    private AutoEquipment $comfort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV Рестайлинг', 'url' => 'iv-restyling']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан', 'url' => 'sedan']);
        $modification = AutoModification::factory()->for($this->series, 'series')->create();
        $this->comfort = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);

        $dimensions = AutoCharacteristic::factory()->create(['name' => 'Размеры']);
        $clearance = AutoCharacteristic::factory()->parameter($dimensions)->create(['name' => 'Клиренс']);
        $suspension = AutoCharacteristic::factory()->parameter($dimensions, null)->create(['name' => 'Подвеска']);
        (new EquipmentCharacteristics)->sync($this->comfort, [$clearance->public_id => '160,5', $suspension->public_id => 'Независимая']);

        $safety = AutoOption::factory()->create(['name' => 'Безопасность']);
        $abs = AutoOption::factory()->option($safety)->create(['name' => 'ABS']);
        $heated = AutoOption::factory()->option($safety)->create(['name' => 'Подогрев сидений']);
        AutoOption::factory()->option($safety)->create(['name' => 'Неизвестная опция']);
        (new EquipmentOptions)->sync($this->comfort, [$abs->public_id => OptionAvailability::Standard, $heated->public_id => OptionAvailability::Optional]);

        $white = SeriesMediaSet::factory()->forSeries($this->series)->create(['name' => 'Белый', 'swatch_hex' => '#ffffff']);
        SeriesMediaImage::factory()->for($white, 'set')->create(['angle' => MediaAngle::FrontThreeQuarter]);
    }

    public function test_site_bindings_expose_display_ready_visible_vehicles_and_offers_only(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($this->comfort)->create([
            'price_minor' => 150_000_000,
            'rrp_minor' => 160_000_000,
            'availability' => OfferAvailability::InStock,
            'badge' => 'Хит',
        ]);
        $offer->replaceBenefits([['type' => BenefitType::Discount, 'amount_minor' => 10_000_000]]);
        SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($this->comfort)->create(['price_minor' => 1_000_000, 'status' => false]);
        $hiddenEquipment = AutoEquipment::factory()->for($this->comfort->modification, 'modification')->create(['name' => 'Classic']);
        SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($hiddenEquipment)->create(['price_minor' => 2_000_000]);
        $hiddenEquipment->update(['status' => false]);

        SiteVehicle::factory()->for($this->site)->forSeries(AutoSeries::factory()->create())->create(['status' => false]);
        SiteVehicle::factory()->for($this->site)->forSeries(AutoSeries::factory()->inactive()->create())->create();
        SiteVehicle::factory()->forSeries($this->series)->create();

        $bindings = app(VehicleBindings::class)->forSite($this->site);

        $this->assertCount(1, $bindings);
        $binding = $bindings[0];
        $this->assertSame($vehicle->public_id, $binding['public_id']);
        $this->assertSame(['Kia', 'Rio', 'IV Рестайлинг', 'Седан', 'Kia Rio'], [$binding['mark'], $binding['model'], $binding['generation'], $binding['series'], $binding['title']]);
        $this->assertSame("1\u{00A0}500\u{00A0}000\u{00A0}₽", $binding['price_from_label']);
        $this->assertSame("100\u{00A0}000\u{00A0}₽", $binding['benefit_up_to_label']);
        $this->assertSame('global', $binding['media']['source']);
        $this->assertSame('#ffffff', $binding['media']['sets'][0]['swatch_hex']);
        $this->assertSame(MediaAngle::FrontThreeQuarter->label(), $binding['media']['sets'][0]['images'][0]['label']);

        $this->assertCount(1, $binding['offers']);
        $view = $binding['offers'][0];
        $this->assertSame($offer->public_id, $view['public_id']);
        $this->assertSame('Comfort', $view['equipment']['name']);
        $this->assertSame("1\u{00A0}600\u{00A0}000\u{00A0}₽", $view['rrp_label']);
        $this->assertSame(OfferAvailability::InStock->label(), $view['availability_label']);
        $this->assertSame('Хит', $view['badge']);
        $this->assertSame([['label' => BenefitType::Discount->label(), 'amount_label' => "100\u{00A0}000\u{00A0}₽"]], $view['benefits']);
        $this->assertStringContainsString('1,6 л', $view['modification']['summary']);
        $this->assertContains(['label' => 'Мощность', 'value' => '123 л.с.'], $view['modification']['specs']);
        $this->assertSame([['group' => 'Размеры', 'items' => [
            ['label' => 'Клиренс', 'value' => '160,5 мм'],
            ['label' => 'Подвеска', 'value' => 'Независимая'],
        ]]], $view['characteristics']);
        $this->assertSame([['group' => 'Безопасность', 'items' => [
            ['name' => 'ABS', 'availability' => 'standard'],
            ['name' => 'Подогрев сидений', 'availability' => 'optional'],
        ]]], $view['options']);

        $json = (string) json_encode($bindings);
        $this->assertStringNotContainsString('"id"', $json);
        $this->assertStringNotContainsString('_minor', $json);
        $this->assertStringNotContainsString('site_id', $json);
    }

    public function test_vehicle_block_field_accepts_only_vehicles_of_the_same_site(): void
    {
        $page = Page::factory()->for($this->site)->create();
        $own = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $foreign = SiteVehicle::factory()->forSeries($this->series)->create();
        $schema = ['fields' => [['key' => 'vehicle', 'type' => 'vehicle', 'label' => 'Автомобиль']]];
        $validator = new BlockStateValidator;
        $references = new PageBlockReferences($page);

        $this->assertSame([], $validator->errors($schema, ['vehicle' => $own->public_id], $references));
        $this->assertSame([], $validator->errors($schema, ['vehicle' => null], $references));
        $this->assertSame(['state.vehicle' => 'Автомобиль не найден на этом сайте.'], $validator->errors($schema, ['vehicle' => $foreign->public_id], $references));
        $this->assertSame(['state.vehicle' => 'Выберите автомобиль этого сайта.'], $validator->errors($schema, ['vehicle' => '42'], $references));
    }

    public function test_designer_and_preview_receive_vehicle_bindings(): void
    {
        $workspace = $this->site->workspace;
        $user = User::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner);
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $this->site->pages()->save($home);
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($this->comfort)->create();

        foreach (['sites.designer' => 'sites/designer', 'sites.preview' => 'sites/preview'] as $route => $component) {
            $this->as($user, $workspace)
                ->get(route($route, $this->site))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->has('vehicles', 1)
                    ->where('vehicles.0.public_id', $vehicle->public_id)
                    ->where('vehicles.0.title', 'Kia Rio')
                    ->has('vehicles.0.offers', 1));
        }
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
