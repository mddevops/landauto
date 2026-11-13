<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleBindings;
use App\Automotive\VehiclePhotos;
use App\Enums\MediaAngle;
use App\Enums\SiteStatus;
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
use App\Models\VehicleCollection;
use App\Models\VehiclePhoto;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogMedia;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class WorkspaceVehicleLibraryTest extends TestCase
{
    use CreatesCatalogMedia, RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private User $owner;

    private AutoSeries $series;

    private AutoEquipment $comfort;

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
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $this->comfort = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($this->series, 'series'), 'modification')
            ->create(['name' => 'Comfort']);
    }

    public function test_owner_adds_vehicles_to_the_catalog_with_prices_and_collections(): void
    {
        $white = $this->colorSet($this->series, 'Белый', [MediaAngle::FrontThreeQuarterLeft]);
        $collection = $this->collection('Акция');
        $foreignCollection = new VehicleCollection(['name' => 'Чужая', 'sort_order' => 0]);
        $foreignCollection->workspace()->associate(Workspace::factory()->create())->save();
        $ref = (string) $this->series->ref();

        $response = $this->as($this->owner)->post(route('workspace.vehicles.store'), [
            'series' => $ref,
            'colors' => [$white->public_id],
            'offers' => [['equipment' => $this->comfort->external_key, 'price' => '1 500 000', 'rrp' => '1 600 000']],
            'collections' => [$collection->public_id, $foreignCollection->public_id],
        ]);
        $vehicle = WorkspaceVehicle::query()->sole();

        $response->assertSessionHasNoErrors()->assertRedirect(route('workspace.vehicles.show', $vehicle->public_id));
        $this->assertSame([$this->workspace->id, $ref], [$vehicle->workspace_id, $vehicle->catalog_series_ref]);
        $this->assertSame([[150_000_000, 160_000_000]], $vehicle->offers()->get()->map(fn ($offer): array => [$offer->price_minor, $offer->rrp_minor])->all());
        $this->assertSame([$collection->public_id], $vehicle->collections()->pluck('vehicle_collections.public_id')->all());
        $this->assertSame(1, $vehicle->photos()->count());

        // The same Series can be added again, e.g. with other colours or prices.
        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => $ref])->assertSessionHasNoErrors();
        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => (string) $this->series->id])->assertSessionHasErrors('series');
        $this->assertSame(2, WorkspaceVehicle::query()->count());

        $this->as($this->owner)
            ->get(route('workspace.vehicles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/vehicles/index')
                ->has('vehicles', 2)
                ->where('vehicles.0.public_id', $vehicle->public_id)
                ->where('vehicles.0.title', 'Kia Rio')
                ->where('vehicles.0.mark.url', 'kia')
                ->where('vehicles.0.model.name', 'Rio')
                ->where('vehicles.0.price_from', "от 1\u{00A0}500\u{00A0}000\u{00A0}₽")
                ->where('vehicles.0.collections', [$collection->public_id])
                ->where('collections.0.vehicles_count', 1)
                ->where('sites.0.public_id', $this->site->public_id)
                ->missing('vehicles.0.id')
                ->missing('vehicles.0.workspace_id'));

        $this->as($this->owner)
            ->get(route('workspace.vehicles.create', ['collection' => $collection->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/vehicles/create')
                ->where('preselectedCollection', $collection->public_id)
                ->missing('marks.0.id'));

        $this->as($this->owner)
            ->get(route('workspace.vehicles.show', $vehicle->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/vehicles/show')
                ->where('vehicle.catalog.ref', $ref)
                ->where('vehicle.colors.0.name', 'Белый')
                ->where('offers.0.name', 'Comfort')
                ->where('offers.0.price', '1500000')
                ->where('sites.0.has_vehicle', false)
                ->missing('sites.0.id'));
    }

    public function test_default_trims_are_replaced_and_validated(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();
        $foreign = AutoEquipment::factory()->create();
        $put = fn (array $offers) => $this->as($this->owner)->put(route('workspace.vehicles.offers', $vehicle->public_id), ['offers' => $offers]);

        $put([['equipment' => $this->comfort->external_key, 'price' => '1 000 000']])->assertSessionHasNoErrors();
        $this->assertSame([100_000_000], $vehicle->offers()->pluck('price_minor')->all());

        $put([['equipment' => $foreign->external_key]])->assertSessionHasErrors('offers');
        $put([['equipment' => $this->comfort->external_key, 'price' => 'abc']])->assertSessionHasErrors('offers.0.price');
        $this->assertSame([100_000_000], $vehicle->offers()->pluck('price_minor')->all());

        $put([])->assertSessionHasNoErrors();
        $this->assertSame(0, $vehicle->offers()->count());
    }

    public function test_members_without_the_catalog_permission_are_denied(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();
        $siteVehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::PricingManager] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);

            $this->as($member)->get(route('workspace.vehicles.index'))->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.store'), ['series' => $this->series->ref()])->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.store'), [])->assertForbidden();
            $this->as($member)->get(route('workspace.vehicles.show', $vehicle->public_id))->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.bulk'), ['action' => 'delete', 'vehicles' => [$vehicle->public_id]])->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.collections.store'), ['name' => 'Новая'])->assertForbidden();
            $this->as($member)->put(route('workspace.vehicles.angle', $vehicle->public_id), ['primary_angle' => 'front'])->assertForbidden();
            $this->as($member)->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))->assertForbidden();
        }

        $this->assertModelExists($vehicle);
        $this->assertSame(0, VehicleCollection::query()->count());
        $this->assertSame(0, SiteVehicle::query()->whereNotNull('source_workspace_vehicle_id')->count());
    }

    public function test_another_workspace_cannot_view_change_or_copy_the_catalog_vehicle(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create(['custom_name' => 'Rio Север']);
        $collection = $this->collection('Акция');
        $other = Workspace::factory()->create();
        $otherSite = Site::factory()->for($other)->create();
        $intruder = User::factory()->create();
        $other->addMember($intruder, WorkspaceRole::Owner);
        $as = fn () => $this->actingAs($intruder)->withSession([WorkspaceContext::SESSION_KEY => $other->public_id]);

        $as()->get(route('workspace.vehicles.show', $vehicle->public_id))->assertNotFound();
        $as()->patch(route('workspace.vehicles.update', $vehicle->public_id), ['custom_name' => 'Чужой'])->assertNotFound();
        $as()->put(route('workspace.vehicles.angle', $vehicle->public_id), ['primary_angle' => 'front'])->assertNotFound();
        $as()->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $otherSite->public_id])->assertNotFound();
        $as()->delete(route('workspace.vehicles.destroy', $vehicle->public_id))->assertNotFound();
        $as()->delete(route('workspace.vehicles.collections.destroy', $collection->public_id))->assertNotFound();
        $as()->post(route('workspace.vehicles.bulk'), ['action' => 'delete', 'vehicles' => [$vehicle->public_id]])->assertSessionHasNoErrors();
        $as()->get(route('workspace.vehicles.index'))->assertInertia(fn (Assert $page) => $page->has('vehicles', 0));

        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $otherSite->public_id])->assertSessionHasErrors('site');

        $this->assertSame('Rio Север', $vehicle->fresh()?->custom_name);
        $this->assertModelExists($collection);
        $this->assertSame(0, SiteVehicle::query()->count());
    }

    public function test_add_to_site_copies_photos_and_priced_trims_that_never_sync_back(): void
    {
        $catalogBefore = $this->series->fresh()?->getAttributes();
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create([
            'custom_name' => 'Kia Rio Север',
            'custom_description' => 'Городской седан',
        ]);
        $photos = app(VehiclePhotos::class);
        $photos->copyFromLibrary($vehicle, [$this->colorSet($this->series, 'Белый', [MediaAngle::Front])->public_id], [$this->galleryImage($this->series)->public_id]);
        $vehicle->update(['primary_angle' => MediaAngle::Front]);
        $this->as($this->owner)->put(route('workspace.vehicles.offers', $vehicle->public_id), ['offers' => [['equipment' => $this->comfort->external_key, 'price' => '1 500 000']]]);

        $this->as($this->owner)
            ->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $copy = SiteVehicle::query()->sole();
        $this->assertSame([$this->site->id, $vehicle->catalog_series_ref, 'Kia Rio Север', 'Городской седан', $vehicle->id, MediaAngle::Front], [
            $copy->site_id, $copy->catalog_series_ref, $copy->custom_name, $copy->custom_description, $copy->source_workspace_vehicle_id, $copy->primary_angle,
        ]);
        $this->assertSame(['Белый'], $copy->colors()->pluck('name')->all());
        $this->assertSame(2, $copy->photos()->count());
        $this->assertSame([], array_intersect($copy->photos()->pluck('path')->all(), $vehicle->photos()->pluck('path')->all()), 'photos are copied files');
        $this->assertSame([150_000_000], $copy->offers()->pluck('price_minor')->all());

        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])->assertSessionHasErrors('site');

        $vehicle->update(['custom_name' => 'Новое имя']);
        $photos->removeAll($vehicle);
        $copy->refresh();
        $this->assertSame('Kia Rio Север', $copy->custom_name);
        $this->assertSame(2, $copy->photos()->count());
        $copy->photos()->get()->each(fn (VehiclePhoto $photo) => Storage::disk('local')->assertExists($photo->path));

        $this->as($this->owner)->delete(route('workspace.vehicles.destroy', $vehicle->public_id))->assertRedirect(route('workspace.vehicles.index'));
        $copy->refresh();
        $this->assertNull($copy->source_workspace_vehicle_id);
        $this->assertSame($catalogBefore, $this->series->fresh()?->getAttributes());
    }

    public function test_add_to_site_rejects_archived_entries_and_archived_sites(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create(['status' => false]);

        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])->assertSessionHasErrors('site');

        $vehicle->update(['status' => true]);
        $archived = Site::factory()->for($this->workspace)->create(['status' => SiteStatus::Archived]);
        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $archived->public_id])->assertSessionHasErrors('site');
        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => (string) $this->site->id])->assertSessionHasErrors('site');

        $this->assertSame(0, SiteVehicle::query()->count());
    }

    public function test_collections_are_unique_per_workspace_and_bulk_actions_work_on_own_vehicles(): void
    {
        $first = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create(['sort_order' => 1]);
        $second = WorkspaceVehicle::factory()->for($this->workspace)->create(['sort_order' => 2]);
        $foreign = WorkspaceVehicle::factory()->forSeries($this->series)->create();

        $this->as($this->owner)->post(route('workspace.vehicles.collections.store'), ['name' => ' Акция '])->assertSessionHasNoErrors();
        $this->as($this->owner)->post(route('workspace.vehicles.collections.store'), ['name' => 'Акция'])->assertSessionHasErrors('name');
        $collection = VehicleCollection::query()->sole();
        $this->assertSame('Акция', $collection->name);

        $bulk = fn (array $data) => $this->as($this->owner)->post(route('workspace.vehicles.bulk'), $data);

        $bulk(['action' => 'add_to_collection', 'collection' => $collection->public_id, 'vehicles' => [$first->public_id, $second->public_id, $foreign->public_id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $collection->vehicles()->pluck('workspace_vehicles.id')->all());

        $bulk(['action' => 'remove_from_collection', 'collection' => $collection->public_id, 'vehicles' => [$second->public_id]])->assertSessionHasNoErrors();
        $this->assertSame([$first->id], $collection->vehicles()->pluck('workspace_vehicles.id')->all());

        $bulk(['action' => 'archive', 'vehicles' => [$first->public_id, $foreign->public_id]])->assertSessionHasNoErrors();
        $this->assertSame([false, true], [$first->fresh()?->status, $foreign->fresh()?->status]);

        $bulk(['action' => 'copy_to_site', 'site' => $this->site->public_id, 'vehicles' => [$first->public_id, $second->public_id]])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'На сайт «Автосалон Север» добавлено автомобилей: 1. Пропущено: 1 — уже есть на сайте или в архиве.');

        $bulk(['action' => 'restore', 'vehicles' => [$first->public_id]])->assertSessionHasNoErrors();
        $bulk(['action' => 'bogus', 'vehicles' => [$first->public_id]])->assertSessionHasErrors('action');
        $bulk(['action' => 'add_to_collection', 'vehicles' => [$first->public_id]])->assertSessionHasErrors('collection');

        $this->as($this->owner)->put(route('workspace.vehicles.collections', $first->public_id), ['collections' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $collection->vehicles()->count());

        $bulk(['action' => 'delete', 'vehicles' => [$first->public_id, $second->public_id]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->workspace->vehicleLibrary()->count());
        $this->assertModelExists($foreign);
        $this->assertSame(1, SiteVehicle::query()->count(), 'Site copies stay');

        $this->as($this->owner)->delete(route('workspace.vehicles.collections.destroy', $collection->public_id))->assertSessionHasNoErrors();
        $this->assertModelMissing($collection);
    }

    public function test_save_to_catalog_copies_photos_and_prices_and_needs_confirmation_to_overwrite(): void
    {
        $siteVehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create([
            'custom_name' => 'Rio на сайте',
            'custom_description' => 'Описание сайта',
        ]);
        app(VehiclePhotos::class)->copyFromLibrary($siteVehicle, [$this->colorSet($this->series, 'Белый')->public_id], []);
        SiteOffer::factory()->for($siteVehicle, 'vehicle')->forEquipment($this->comfort)->create(['price_minor' => 120_000_000]);

        $this->as($this->owner)
            ->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Автомобиль сохранён в Каталог.');

        $library = WorkspaceVehicle::query()->sole();
        $this->assertSame([$this->workspace->id, 'Rio на сайте', 'Описание сайта'], [$library->workspace_id, $library->custom_name, $library->custom_description]);
        $this->assertSame(['Белый'], $library->colors()->pluck('name')->all());
        $this->assertSame([120_000_000], $library->offers()->pluck('price_minor')->all());

        $siteVehicle->update(['custom_name' => 'Обновлённый Rio']);
        $this->as($this->owner)->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))->assertSessionHasErrors('library');
        $this->assertSame('Rio на сайте', $library->fresh()?->custom_name);

        $this->as($this->owner)
            ->post(route('sites.vehicles.library', [$this->site, $siteVehicle]), ['overwrite' => true])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Автомобиль в Каталоге обновлён.');
        $this->assertSame('Обновлённый Rio', $library->fresh()?->custom_name);
        $this->assertSame(1, WorkspaceVehicle::query()->count());
        $this->assertSame(1, $library->colors()->count(), 'overwrite replaces photos instead of adding');

        $this->as($this->owner)
            ->get(route('sites.vehicles.show', [$this->site, $siteVehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicle.in_library', true)
                ->where('can.manageLibrary', true)
                ->missing('vehicle.source_workspace_vehicle_id'));
    }

    public function test_site_vehicle_name_is_editable_and_used_in_bindings(): void
    {
        $siteVehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();

        $this->as($this->owner)
            ->patch(route('sites.vehicles.update', [$this->site, $siteVehicle]), ['custom_name' => 'Kia Rio Север', 'custom_description' => 'Описание'])
            ->assertSessionHasNoErrors();
        $this->as($this->owner)
            ->patch(route('sites.vehicles.update', [$this->site, $siteVehicle]), ['custom_name' => str_repeat('я', 121)])
            ->assertSessionHasErrors('custom_name');

        $this->assertSame('Kia Rio Север', $siteVehicle->fresh()?->custom_name);
        $this->assertSame('Kia Rio Север', app(VehicleBindings::class)->forSite($this->site)[0]['title']);
    }

    private function collection(string $name): VehicleCollection
    {
        $collection = new VehicleCollection(['name' => $name, 'sort_order' => 0]);
        $collection->workspace()->associate($this->workspace)->save();

        return $collection;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
