<?php

namespace Tests\Feature\Vehicles;

use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
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

class SiteVehicleFlowTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private User $owner;

    private AutoSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Автосалон Север']);
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV Рестайлинг', 'url' => 'iv-restyling']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан', 'url' => 'sedan']);
    }

    public function test_customer_adds_a_vehicle_by_cascading_catalog_selection(): void
    {
        $generation = $this->series->generation;
        $model = $generation->model;
        $hatch = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Хэтчбек', 'url' => 'hatch', 'sort_order' => 1]);
        AutoSeries::factory()->for($generation, 'generation')->inactive()->create(['name' => 'Скрытая', 'url' => 'hidden']);

        $this->as($this->owner)
            ->get(route('sites.vehicles.create', [
                'site' => $this->site,
                'mark' => $model->mark->public_id,
                'model' => $model->public_id,
                'generation' => $generation->public_id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/create')
                ->has('levels', 4)
                ->where('levels.3.items.0.name', 'Седан')
                ->where('levels.3.items.1.name', 'Хэтчбек')
                ->has('levels.3.items', 2)
                ->missing('levels.0.items.0.id'));

        $response = $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => $this->series->public_id]);
        $vehicle = SiteVehicle::query()->sole();
        $response->assertRedirect(route('sites.vehicles.show', [$this->site, $vehicle]));
        $this->assertSame([$this->site->id, $this->series->public_id], [$vehicle->site_id, $vehicle->catalog_series_public_id]);

        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => $this->series->public_id])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => AutoSeries::query()->where('url', 'hidden')->value('public_id')])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => '1'])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => $hatch->public_id])->assertSessionHasNoErrors();

        $this->as($this->owner)
            ->get(route('sites.vehicles.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/index')
                ->has('vehicles', 2)
                ->where('vehicles.0.catalog.title', 'Kia Rio')
                ->where('vehicles.0.catalog.series', 'Седан')
                ->where('vehicles.0.catalog_available', true)
                ->missing('vehicles.0.id'));
    }

    public function test_vehicle_page_offers_available_modifications_and_selects_media_by_reference(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $modification = AutoModification::factory()->for($this->series, 'series')->create(['name' => '1.6 AT', 'engine_volume' => 1591, 'engine_power' => '123.00']);
        AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);
        AutoEquipment::factory()->for($modification, 'modification')->inactive()->create(['name' => 'Старая']);
        AutoModification::factory()->for($this->series, 'series')->inactive()->create(['name' => 'Снята']);
        $white = SeriesMediaSet::factory()->forSeries($this->series)->create(['name' => 'Белый']);
        SeriesMediaSet::factory()->forSeries($this->series)->create(['name' => 'Выключен', 'status' => false]);
        $foreign = SeriesMediaSet::factory()->create();

        $this->as($this->owner)
            ->get(route('sites.vehicles.show', [$this->site, $vehicle]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/show')
                ->where('vehicle.catalog.generation', 'IV Рестайлинг')
                ->has('modifications', 1)
                ->where('modifications.0.summary', '1,6 л · 123 л.с. · Бензин · Автомат · Передний')
                ->has('modifications.0.equipments', 1)
                ->where('modifications.0.equipments.0.name', 'Comfort')
                ->has('mediaSets', 1)
                ->where('mediaSets.0.selected', false)
                ->where('can.editPrices', true));

        $this->as($this->owner)->put(route('sites.vehicles.media', [$this->site, $vehicle]), ['sets' => [$white->public_id]])->assertSessionHasNoErrors();
        $this->assertSame([$white->public_id], $vehicle->mediaSets()->pluck('series_media_sets.public_id')->all());

        $this->as($this->owner)->put(route('sites.vehicles.media', [$this->site, $vehicle]), ['sets' => [$foreign->public_id]])->assertSessionHasErrors('sets');
        $this->assertSame(1, $vehicle->mediaSets()->count());

        $this->as($this->owner)->put(route('sites.vehicles.media', [$this->site, $vehicle]), ['sets' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $vehicle->mediaSets()->count());

        $this->as($this->owner)->patch(route('sites.vehicles.update', [$this->site, $vehicle]), ['status' => false, 'sort_order' => 3])->assertSessionHasNoErrors();
        $this->assertSame([false, 3], [$vehicle->fresh()?->status, $vehicle->fresh()?->sort_order]);
    }

    public function test_offers_parse_money_on_the_server_and_require_equipment_of_the_vehicle_series(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $modification = AutoModification::factory()->for($this->series, 'series')->create();
        $comfort = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);
        $prestige = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Prestige']);
        $retired = AutoEquipment::factory()->for($modification, 'modification')->inactive()->create();
        $foreign = AutoEquipment::factory()->create();
        $offer = fn (array $overrides = []) => [
            'equipment' => $comfort->public_id,
            'price' => '1 850 000,50',
            'rrp' => '1 990 000',
            'availability' => 'in_stock',
            'badge' => ' Хит ',
            'status' => true,
            'sort_order' => 0,
            'benefits' => [
                ['type' => 'trade_in', 'amount' => '100 000', 'label' => ''],
                ['type' => 'credit', 'amount' => '50000', 'label' => 'При покупке в кредит'],
            ],
            ...$overrides,
        ];
        $store = fn (array $data) => $this->as($this->owner)->post(route('sites.offers.store', [$this->site, $vehicle]), $data);

        $store($offer())->assertSessionHasNoErrors()->assertRedirect();
        $saved = SiteOffer::query()->sole();
        $this->assertSame([185_000_050, 199_000_000, 'RUB', 'Хит'], [$saved->price_minor, $saved->rrp_minor, $saved->currency, $saved->badge]);
        $this->assertSame([10_000_000, 5_000_000], $saved->benefits()->pluck('amount_minor')->all());
        $this->assertSame([null, 'При покупке в кредит'], $saved->benefits()->pluck('label')->all());

        $store($offer(['equipment' => $foreign->public_id]))->assertSessionHasErrors('equipment');
        $store($offer(['equipment' => $retired->public_id]))->assertSessionHasErrors('equipment');
        $store($offer(['price' => '1.850.000']))->assertSessionHasErrors('price');
        $store($offer(['price' => '-5']))->assertSessionHasErrors('price');
        $store($offer(['price' => 1850000.5]))->assertSessionHasErrors('price');
        $store($offer(['rrp' => 'abc']))->assertSessionHasErrors('rrp');
        $store($offer(['benefits' => [['type' => 'cashback', 'amount' => '10']]]))->assertSessionHasErrors('benefits.0.type');
        $store($offer(['benefits' => [['type' => 'discount', 'amount' => '0']]]))->assertSessionHasErrors('benefits.0.amount');
        $this->assertSame(1, SiteOffer::query()->count());

        $comfort->update(['status' => false]);
        $this->as($this->owner)
            ->patch(route('sites.offers.update', [$this->site, $saved]), $offer(['price' => '1799000', 'benefits' => []]))
            ->assertSessionHasNoErrors();
        $saved->refresh();
        $this->assertSame([179_900_000, 0], [$saved->price_minor, $saved->benefits()->count()]);

        $this->as($this->owner)
            ->patch(route('sites.offers.update', [$this->site, $saved]), $offer(['equipment' => $prestige->public_id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($prestige->public_id, $saved->fresh()?->catalog_equipment_public_id);

        $this->as($this->owner)
            ->get(route('sites.vehicles.show', [$this->site, $vehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.0.equipment.name', 'Prestige')
                ->where('offers.0.price', '1850000,50')
                ->where('offers.0.price_label', "1\u{00A0}850\u{00A0}000,50\u{00A0}₽")
                ->missing('offers.0.price_minor'));

        $this->as($this->owner)->delete(route('sites.offers.destroy', [$this->site, $saved]))->assertRedirect();
        $this->assertModelMissing($saved);

        $this->as($this->owner)->delete(route('sites.vehicles.destroy', [$this->site, $vehicle]))->assertRedirect(route('sites.vehicles.index', $this->site));
        $this->assertModelMissing($vehicle);
        $this->assertSame(4, AutoEquipment::query()->count(), 'customer actions never touch the catalog');
    }

    public function test_foreign_workspace_and_cross_site_references_are_not_found(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->create();
        $otherSite = Site::factory()->for($this->workspace)->create();
        $stranger = User::factory()->create();
        $strangerWorkspace = Workspace::factory()->create();
        $strangerWorkspace->addMember($stranger, WorkspaceRole::Owner);

        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $strangerWorkspace->public_id])
            ->get(route('sites.vehicles.show', [$this->site, $vehicle]))->assertNotFound();
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $strangerWorkspace->public_id])
            ->get(route('sites.vehicles.index', $this->site))->assertNotFound();
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $strangerWorkspace->public_id])
            ->delete(route('sites.offers.destroy', [$this->site, $offer]))->assertNotFound();

        $this->as($this->owner)->get(route('sites.vehicles.show', [$otherSite, $vehicle]))->assertNotFound();
        $this->as($this->owner)->patch(route('sites.offers.update', [$otherSite, $offer]), ['price' => '1'])->assertNotFound();
        $this->as($this->owner)->post(route('sites.offers.store', [$otherSite, $vehicle]), ['price' => '1'])->assertNotFound();

        $this->assertModelExists($offer);
    }

    public function test_vehicle_access_follows_workspace_role_permissions(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $members = [];

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $members[$role->value] = User::factory()->create();
            $this->workspace->addMember($members[$role->value], $role);
        }

        $this->as($members['admin'])->get(route('sites.vehicles.show', [$this->site, $vehicle]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.editVehicles', true)->where('can.editPrices', true));

        foreach (['designer', 'content_editor'] as $role) {
            $this->as($members[$role])->get(route('sites.vehicles.index', $this->site))->assertForbidden();
            $this->as($members[$role])->get(route('sites.vehicles.create', $this->site))->assertForbidden();
            $this->as($members[$role])->post(route('sites.vehicles.store', $this->site), ['series' => $this->series->public_id])->assertForbidden();
            $this->as($members[$role])->put(route('sites.vehicles.media', [$this->site, $vehicle]), ['sets' => []])->assertForbidden();
            $this->as($members[$role])->post(route('sites.offers.store', [$this->site, $vehicle]), ['price' => '1'])->assertForbidden();
        }

        $this->as($this->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canViewVehicles', true));
        $this->as($members['designer'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canViewVehicles', false));
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
