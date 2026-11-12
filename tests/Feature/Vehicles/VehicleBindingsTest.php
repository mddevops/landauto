<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleBindings;
use App\Automotive\VehiclePhotos;
use App\Blocks\BlockStateValidator;
use App\Blocks\PageBlockReferences;
use App\Enums\BenefitType;
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
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogMedia;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VehicleBindingsTest extends TestCase
{
    use CreatesCatalogMedia, RefreshCatalogDatabase, RefreshDatabase;

    private Site $site;

    private AutoSeries $series;

    private AutoEquipment $comfort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV Рестайлинг']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $modification = AutoModification::factory()->for($this->series, 'series')->create();
        $this->comfort = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);

        $dimensions = AutoCharacteristic::factory()->create(['name' => 'Размеры']);
        $catalog = DB::connection('catalog');

        foreach ([['Клиренс', '160,5', 'мм', 1], ['Подвеска', 'Независимая', null, 2]] as [$name, $value, $unit, $sort]) {
            $characteristic = AutoCharacteristic::factory()->create(['name' => $name, 'parent_id' => $dimensions->id, 'sort' => $sort]);
            $catalog->table('auto_characteristic_values')->insert([
                'modification_id' => $modification->id, 'characteristic_id' => $characteristic->id, 'value' => $value, 'unit' => $unit,
            ]);
        }

        $safety = AutoOption::query()->create(['name' => 'Безопасность', 'parent_id' => null, 'sort' => 0]);

        foreach ([['ABS', true, 1], ['Подогрев сидений', false, 2]] as [$name, $base, $sort]) {
            $option = AutoOption::query()->create(['name' => $name, 'parent_id' => $safety->id, 'sort' => $sort]);
            $catalog->table('auto_option_values')->insert(['equipment_id' => $this->comfort->id, 'option_id' => $option->id, 'is_base' => $base]);
        }

        AutoOption::query()->create(['name' => 'Неизвестная опция', 'parent_id' => $safety->id, 'sort' => 3]);
    }

    private function withPhotos(SiteVehicle $vehicle): void
    {
        Storage::fake('local');
        $white = $this->colorSet($this->series, 'Белый', [MediaAngle::FrontThreeQuarterLeft]);
        $white->update(['swatch_hex' => '#ffffff']);
        app(VehiclePhotos::class)->copyFromLibrary($vehicle, [$white->public_id], []);
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
        $removedEquipment = AutoEquipment::factory()->for($this->comfort->modification, 'modification')->create(['name' => 'Classic']);
        SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($removedEquipment)->create(['price_minor' => 2_000_000]);
        $removedEquipment->delete();
        $onRequest = AutoEquipment::factory()->for($this->comfort->modification, 'modification')->create(['name' => 'Luxe']);
        SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($onRequest)->create(['price_minor' => null]);
        $this->withPhotos($vehicle);

        SiteVehicle::factory()->for($this->site)->forSeries(AutoSeries::factory()->create())->create(['status' => false]);
        $removedSeries = AutoSeries::factory()->create();
        SiteVehicle::factory()->for($this->site)->forSeries($removedSeries)->create();
        $removedSeries->delete();
        SiteVehicle::factory()->forSeries($this->series)->create();

        $bindings = app(VehicleBindings::class)->forSite($this->site);

        $this->assertCount(1, $bindings);
        $binding = $bindings[0];
        $this->assertSame($vehicle->public_id, $binding['public_id']);
        $this->assertSame(['Kia', 'Rio', 'IV Рестайлинг', 'Седан', 'Kia Rio'], [$binding['mark'], $binding['model'], $binding['generation'], $binding['series'], $binding['title']]);
        $this->assertArrayNotHasKey('description', $binding);
        $this->assertSame("1\u{00A0}500\u{00A0}000\u{00A0}₽", $binding['price_from_label']);
        $this->assertSame("100\u{00A0}000\u{00A0}₽", $binding['benefit_up_to_label']);

        $vehicle->update(['custom_name' => 'Rio для города', 'custom_description' => "Экономичный седан.\nГотов к выдаче."]);
        $binding = app(VehicleBindings::class)->forSite($this->site)[0];
        $this->assertSame(['Rio для города', "Экономичный седан.\nГотов к выдаче."], [$binding['title'], $binding['description']]);
        $this->assertSame('site', $binding['media']['source']);
        $this->assertSame('#ffffff', $binding['media']['sets'][0]['swatch_hex']);
        $this->assertSame(MediaAngle::FrontThreeQuarterLeft->label(), $binding['media']['sets'][0]['images'][0]['label']);

        $this->assertCount(2, $binding['offers']);
        $this->assertSame(['Luxe', null], [$binding['offers'][1]['equipment']['name'], $binding['offers'][1]['price_label']]);
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
