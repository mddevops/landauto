<?php

namespace Tests\Feature\Vehicles;

use App\Enums\MediaAngle;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\VehiclePhoto;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogMedia;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteVehicleFlowTest extends TestCase
{
    use CreatesCatalogMedia, RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private User $owner;

    private AutoSeries $series;

    private AutoEquipment $comfort;

    private AutoEquipment $prestige;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Автосалон Север']);
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV Рестайлинг']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $modification = AutoModification::factory()->for($this->series, 'series')->create(['engine_volume' => '1.6', 'engine_power' => 123]);
        $this->comfort = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);
        $this->prestige = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Prestige']);
    }

    public function test_wizard_walks_the_catalog_by_partial_reloads(): void
    {
        $generation = $this->series->generation;
        AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Хэтчбек']);
        $white = $this->colorSet($this->series, 'Белый', [MediaAngle::FrontThreeQuarterLeft, MediaAngle::Front]);
        $this->colorSet($this->series, 'Выключен', active: false);
        $this->galleryImage($this->series);
        $ref = (string) $this->series->ref();

        $this->as($this->owner)
            ->get(route('sites.vehicles.create', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/create')
                ->where('marks.0.url', 'kia')
                ->where('models', null)
                ->where('picked', null)
                ->where('can.editPrices', true)
                ->missing('marks.0.id'));

        $this->as($this->owner)
            ->get(route('sites.vehicles.create', ['site' => $this->site, 'mark' => 'kia', 'model' => 'rio']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('models.items.0.name', 'Rio')
                ->where('generations.items.0.name', 'IV Рестайлинг')
                ->has('generations.items.0.series', 2)
                ->where('generations.items.0.series.0.ref', $ref)
                ->where('generations.items.0.series.0.colors_count', 2)
                ->where('generations.items.0.series.0.gallery_count', 1));

        $this->as($this->owner)
            ->get(route('sites.vehicles.create', ['site' => $this->site, 'series' => $ref]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('picked.series.title', 'Kia Rio')
                ->has('picked.library.colors', 1)
                ->where('picked.library.colors.0.public_id', $white->public_id)
                ->where('picked.library.colors.0.images.0.angle', 'front_3_4_left')
                ->has('picked.library.gallery', 1)
                ->where('picked.trims.0.summary', '1,6 л · 123 л.с. · Бензин · Автомат · Передний')
                ->where('picked.trims.0.equipments.0.key', $this->comfort->external_key)
                ->has('angles', 5));
    }

    public function test_customer_adds_a_vehicle_with_photos_and_prices(): void
    {
        $white = $this->colorSet($this->series, 'Белый', [MediaAngle::FrontThreeQuarterLeft, MediaAngle::Front]);
        $gallery = $this->galleryImage($this->series);
        $ref = (string) $this->series->ref();

        $response = $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), [
            'series' => $ref,
            'custom_name' => ' Rio для города ',
            'colors' => [$white->public_id],
            'gallery' => [$gallery->public_id],
            'primary_angle' => 'front',
            'uploads' => [UploadedFile::fake()->image('salon.jpg', 800, 600)],
            'offers' => [
                ['equipment' => $this->comfort->external_key, 'price' => '1 650 000', 'rrp' => '1 790 000'],
                ['equipment' => $this->prestige->external_key, 'price' => '', 'rrp' => ''],
            ],
        ]);

        $vehicle = SiteVehicle::query()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('sites.vehicles.show', [$this->site, $vehicle]));
        $this->assertSame([$ref, 'Rio для города', MediaAngle::Front], [$vehicle->catalog_series_ref, $vehicle->custom_name, $vehicle->primary_angle]);
        $this->assertSame(['Белый'], $vehicle->colors()->pluck('name')->all());
        $this->assertSame(4, $vehicle->photos()->count(), 'two angles, one gallery photo, one upload');
        $this->assertSame([VehiclePhoto::SOURCE_CATALOG, VehiclePhoto::SOURCE_UPLOAD], $vehicle->photos()->distinct()->orderBy('source')->pluck('source')->all());
        $vehicle->photos()->get()->each(fn (VehiclePhoto $photo) => Storage::disk('local')->assertExists($photo->path));
        $this->assertSame([[165_000_000, 179_000_000], [null, null]], $vehicle->offers()->ordered()->get()->map(fn (SiteOffer $offer): array => [$offer->price_minor, $offer->rrp_minor])->all());

        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => $ref])->assertSessionHasErrors('series');
        $this->assertSame(1, SiteVehicle::query()->count());

        $this->as($this->owner)
            ->get(route('sites.vehicles.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/index')
                ->has('vehicles', 1)
                ->where('vehicles.0.title', 'Rio для города')
                ->where('vehicles.0.mark.name', 'Kia')
                ->where('vehicles.0.offers_count', 2)
                ->where('vehicles.0.swatches.0.name', 'Белый')
                ->where('vehicles.0.catalog_available', true)
                ->missing('vehicles.0.id'));

        $this->as($this->owner)
            ->get(route('sites.vehicles.show', [$this->site, $vehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/show')
                ->where('vehicle.catalog.generation.name', 'IV Рестайлинг')
                ->where('vehicle.primary_angle', 'front')
                ->has('vehicle.colors.0.photos', 2)
                ->has('vehicle.gallery', 2)
                ->where('offers.0.equipment', $this->comfort->external_key)
                ->where('offers.0.name', 'Comfort')
                ->where('offers.0.price_label', "1\u{00A0}650\u{00A0}000\u{00A0}₽")
                ->where('offers.1.price_label', null)
                ->missing('offers.0.price_minor'));
    }

    public function test_wizard_rejects_foreign_photos_trims_and_bad_prices(): void
    {
        $other = AutoSeries::factory()->create();
        $foreignColor = $this->colorSet($other, 'Чужой');
        $foreignTrim = AutoEquipment::factory()->create();
        $ref = (string) $this->series->ref();
        $store = fn (array $data) => $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => $ref, ...$data]);

        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => '1:2'])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('sites.vehicles.store', $this->site), ['series' => 'kia'])->assertSessionHasErrors('series');
        $store(['colors' => [$foreignColor->public_id]])->assertSessionHasErrors('photos');
        $store(['offers' => [['equipment' => $foreignTrim->external_key]]])->assertSessionHasErrors('offers');
        $store(['offers' => [['equipment' => $this->comfort->external_key], ['equipment' => $this->comfort->external_key]]])->assertSessionHasErrors('offers');
        $store(['offers' => [['equipment' => $this->comfort->external_key, 'price' => '1.850.000']]])->assertSessionHasErrors('offers.0.price');
        $store(['offers' => [['equipment' => $this->comfort->external_key, 'price' => '2 000 000', 'rrp' => '1 000 000']]])->assertSessionHasErrors('offers.0.rrp');
        $store(['uploads' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('uploads.0');

        $this->assertSame(0, SiteVehicle::query()->count());
        $this->assertSame(0, VehiclePhoto::query()->count());
    }

    public function test_offers_parse_money_on_the_server_and_require_equipment_of_the_vehicle_series(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $foreign = AutoEquipment::factory()->create();
        $offer = fn (array $overrides = []) => [
            'equipment' => $this->comfort->external_key,
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

        $store($offer(['equipment' => $foreign->external_key]))->assertSessionHasErrors('equipment');
        $store($offer(['price' => '1.850.000']))->assertSessionHasErrors('price');
        $store($offer(['price' => '-5']))->assertSessionHasErrors('price');
        $store($offer(['rrp' => 'abc']))->assertSessionHasErrors('rrp');
        $store($offer(['price' => '2 000 000', 'rrp' => '1 000 000']))->assertSessionHasErrors('rrp');
        $store($offer(['benefits' => [['type' => 'cashback', 'amount' => '10']]]))->assertSessionHasErrors('benefits.0.type');
        $store($offer(['benefits' => [['type' => 'discount', 'amount' => '0']]]))->assertSessionHasErrors('benefits.0.amount');
        $this->assertSame(1, SiteOffer::query()->count());

        $this->as($this->owner)
            ->patch(route('sites.offers.update', [$this->site, $saved]), $offer(['price' => '', 'rrp' => '', 'benefits' => []]))
            ->assertSessionHasNoErrors();
        $saved->refresh();
        $this->assertSame([null, null, 0], [$saved->price_minor, $saved->rrp_minor, $saved->benefits()->count()]);

        $this->as($this->owner)
            ->patch(route('sites.offers.update', [$this->site, $saved]), $offer(['equipment' => $this->prestige->external_key]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->prestige->external_key, $saved->fresh()?->catalog_equipment_ref);

        $this->as($this->owner)
            ->get(route('sites.vehicles.show', [$this->site, $vehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.0.name', 'Prestige')
                ->where('offers.0.price', '1850000,50')
                ->where('offers.0.price_label', "1\u{00A0}850\u{00A0}000,50\u{00A0}₽")
                ->missing('offers.0.price_minor'));

        $this->as($this->owner)->delete(route('sites.offers.destroy', [$this->site, $saved]))->assertRedirect();
        $this->assertModelMissing($saved);

        $this->as($this->owner)->delete(route('sites.vehicles.destroy', [$this->site, $vehicle]))->assertRedirect(route('sites.vehicles.index', $this->site));
        $this->assertModelMissing($vehicle);
        $this->assertSame(3, AutoEquipment::query()->count(), 'customer actions never touch the catalog');
    }

    public function test_photos_colours_and_primary_angle_are_edited_on_the_vehicle_page(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $white = $this->colorSet($this->series, 'Белый', [MediaAngle::Front]);
        $gallery = $this->galleryImage($this->series);

        $this->as($this->owner)->post(route('sites.vehicles.photos.library', [$this->site, $vehicle]), ['colors' => [$white->public_id], 'gallery' => [$gallery->public_id]])
            ->assertSessionHasNoErrors();
        $this->as($this->owner)->post(route('sites.vehicles.photos.library', [$this->site, $vehicle]), ['colors' => [$white->public_id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Белый'], $vehicle->colors()->pluck('name')->all(), 'a colour already on the vehicle is not copied twice');
        $this->assertSame(2, $vehicle->photos()->count());

        $this->as($this->owner)->post(route('sites.vehicles.colors.store', [$this->site, $vehicle]), ['name' => 'Синий', 'swatch_hex' => '#0000ff'])
            ->assertSessionHasNoErrors();
        $blue = $vehicle->colors()->where('name', 'Синий')->sole();
        $this->as($this->owner)->post(route('sites.vehicles.colors.store', [$this->site, $vehicle]), ['name' => 'Красный', 'swatch_hex' => 'red'])
            ->assertSessionHasErrors('swatch_hex');

        $this->as($this->owner)->post(route('sites.vehicles.photos.store', [$this->site, $vehicle]), [
            'files' => [UploadedFile::fake()->image('front.png')],
            'color' => $blue->public_id,
            'angle' => 'side_left',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['side_left'], $blue->photos()->get()->map(fn (VehiclePhoto $photo): ?string => $photo->angle?->value)->all());

        $this->as($this->owner)->post(route('sites.vehicles.photos.store', [$this->site, $vehicle]), [
            'files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
            'color' => $blue->public_id,
            'angle' => 'front',
        ])->assertSessionHasErrors();

        $this->as($this->owner)->put(route('sites.vehicles.angle', [$this->site, $vehicle]), ['primary_angle' => 'side_left'])->assertSessionHasNoErrors();
        $this->assertSame(MediaAngle::SideLeft, $vehicle->fresh()?->primary_angle);

        $galleryPhoto = $vehicle->photos()->whereNull('vehicle_color_id')->sole();
        $this->as($this->owner)->delete(route('sites.vehicles.photos.destroy', [$this->site, $vehicle, $galleryPhoto]))->assertSessionHasNoErrors();
        $this->assertModelMissing($galleryPhoto);
        Storage::disk('local')->assertMissing($galleryPhoto->path);

        $this->as($this->owner)->delete(route('sites.vehicles.colors.destroy', [$this->site, $vehicle, $blue]))->assertSessionHasNoErrors();
        $this->assertSame(['Белый'], $vehicle->colors()->pluck('name')->all());
        $this->assertSame(1, $vehicle->photos()->count());
    }

    public function test_photos_are_served_only_inside_the_owning_workspace(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $this->as($this->owner)->post(route('sites.vehicles.photos.store', [$this->site, $vehicle]), ['files' => [UploadedFile::fake()->image('a.png')]]);
        $photo = $vehicle->photos()->sole();

        $this->as($this->owner)->get($photo->url())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $stranger = User::factory()->create();
        $strangerWorkspace = Workspace::factory()->create();
        $strangerWorkspace->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $strangerWorkspace->public_id])->get($photo->url())->assertNotFound();
        auth()->logout();
        $this->get($photo->url())->assertRedirect();
    }

    public function test_foreign_workspace_and_cross_site_references_are_not_found(): void
    {
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();
        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->forEquipment($this->comfort)->create();
        $otherSite = Site::factory()->for($this->workspace)->create();
        $stranger = User::factory()->create();
        $strangerWorkspace = Workspace::factory()->create();
        $strangerWorkspace->addMember($stranger, WorkspaceRole::Owner);
        $asStranger = fn () => $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $strangerWorkspace->public_id]);

        $asStranger()->get(route('sites.vehicles.show', [$this->site, $vehicle]))->assertNotFound();
        $asStranger()->get(route('sites.vehicles.index', $this->site))->assertNotFound();
        $asStranger()->post(route('sites.vehicles.store', $this->site), ['series' => $this->series->ref()])->assertNotFound();
        $asStranger()->delete(route('sites.offers.destroy', [$this->site, $offer]))->assertNotFound();

        $this->as($this->owner)->get(route('sites.vehicles.show', [$otherSite, $vehicle]))->assertNotFound();
        $this->as($this->owner)->patch(route('sites.offers.update', [$otherSite, $offer]), ['price' => '1'])->assertNotFound();
        $this->as($this->owner)->post(route('sites.offers.store', [$otherSite, $vehicle]), ['price' => '1'])->assertNotFound();
        $this->as($this->owner)->put(route('sites.vehicles.angle', [$otherSite, $vehicle]), ['primary_angle' => 'front'])->assertNotFound();

        $this->assertModelExists($offer);
        $this->assertSame(0, $strangerWorkspace->sites()->count());
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
            $this->as($members[$role])->post(route('sites.vehicles.store', $this->site), ['series' => $this->series->ref()])->assertForbidden();
            $this->as($members[$role])->post(route('sites.vehicles.store', $this->site), [])->assertForbidden();
            $this->as($members[$role])->post(route('sites.vehicles.photos.store', [$this->site, $vehicle]), ['files' => [UploadedFile::fake()->image('a.png')]])->assertForbidden();
            $this->as($members[$role])->put(route('sites.vehicles.angle', [$this->site, $vehicle]), ['primary_angle' => 'front'])->assertForbidden();
            $this->as($members[$role])->post(route('sites.offers.store', [$this->site, $vehicle]), ['price' => '1'])->assertForbidden();
        }

        $this->assertSame(0, VehiclePhoto::query()->count());
        $this->as($this->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canViewVehicles', true));
        $this->as($members['designer'])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canViewVehicles', false));
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
