<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleBindings;
use App\Enums\SiteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class WorkspaceVehicleLibraryTest extends TestCase
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
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV', 'url' => 'iv']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан', 'url' => 'sedan']);
    }

    public function test_owner_adds_a_catalog_series_to_the_library_once(): void
    {
        $response = $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => $this->series->public_id]);
        $vehicle = WorkspaceVehicle::query()->sole();

        $response->assertRedirect(route('workspace.vehicles.show', $vehicle->public_id));
        $this->assertSame([$this->workspace->id, $this->series->public_id], [$vehicle->workspace_id, $vehicle->catalog_series_public_id]);

        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => $this->series->public_id])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'])->assertSessionHasErrors('series');
        $this->as($this->owner)->post(route('workspace.vehicles.store'), ['series' => (string) $this->series->id])->assertSessionHasErrors('series');
        $this->assertSame(1, WorkspaceVehicle::query()->count());

        $this->as($this->owner)
            ->get(route('workspace.vehicles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/vehicles/index')
                ->has('vehicles', 1)
                ->where('vehicles.0.public_id', $vehicle->public_id)
                ->where('vehicles.0.catalog.title', 'Kia Rio')
                ->missing('vehicles.0.id')
                ->missing('vehicles.0.workspace_id'));

        $this->as($this->owner)
            ->get(route('workspace.vehicles.create', ['mark' => $this->series->generation->model->mark->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('workspaces/vehicles/create')->missing('levels.0.items.0.id'));
    }

    public function test_members_without_the_library_permission_are_denied(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();
        $siteVehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create();

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::PricingManager] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);

            $this->as($member)->get(route('workspace.vehicles.index'))->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.store'), ['series' => $this->series->public_id])->assertForbidden();
            $this->as($member)->get(route('workspace.vehicles.show', $vehicle->public_id))->assertForbidden();
            $this->as($member)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])->assertForbidden();
            $this->as($member)->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))->assertForbidden();
        }

        $this->assertSame(0, SiteVehicle::query()->whereNotNull('source_workspace_vehicle_id')->count());
    }

    public function test_another_workspace_cannot_view_change_or_import_the_library_vehicle(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create(['custom_name' => 'Rio Север']);
        $other = Workspace::factory()->create();
        $otherSite = Site::factory()->for($other)->create();
        $intruder = User::factory()->create();
        $other->addMember($intruder, WorkspaceRole::Owner);

        $as = fn () => $this->actingAs($intruder)->withSession([WorkspaceContext::SESSION_KEY => $other->public_id]);

        $as()->get(route('workspace.vehicles.show', $vehicle->public_id))->assertNotFound();
        $as()->patch(route('workspace.vehicles.update', $vehicle->public_id), ['custom_name' => 'Чужой', 'status' => true])->assertNotFound();
        $as()->put(route('workspace.vehicles.media', $vehicle->public_id), ['sets' => []])->assertNotFound();
        $as()->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $otherSite->public_id])->assertNotFound();
        $as()->delete(route('workspace.vehicles.destroy', $vehicle->public_id))->assertNotFound();
        $as()->get(route('workspace.vehicles.index'))->assertInertia(fn (Assert $page) => $page->has('vehicles', 0));

        // A library vehicle cannot be copied into a Site of another Workspace.
        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $otherSite->public_id])->assertSessionHasErrors('site');

        $this->assertSame('Rio Север', $vehicle->fresh()?->custom_name);
        $this->assertSame(0, SiteVehicle::query()->count());
    }

    public function test_media_sets_must_be_active_sets_of_the_same_series(): void
    {
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();
        $white = SeriesMediaSet::factory()->forSeries($this->series)->create(['name' => 'Белый']);
        $inactive = SeriesMediaSet::factory()->forSeries($this->series)->create(['status' => false]);
        $foreign = SeriesMediaSet::factory()->create();

        $this->as($this->owner)->put(route('workspace.vehicles.media', $vehicle->public_id), ['sets' => [$foreign->public_id]])->assertSessionHasErrors('sets');
        $this->as($this->owner)->put(route('workspace.vehicles.media', $vehicle->public_id), ['sets' => [$inactive->public_id]])->assertSessionHasErrors('sets');
        $this->as($this->owner)->put(route('workspace.vehicles.media', $vehicle->public_id), ['sets' => [$white->public_id]])->assertSessionHasNoErrors();

        $this->assertSame([$white->public_id], $vehicle->mediaSets()->pluck('series_media_sets.public_id')->all());

        $this->as($this->owner)
            ->get(route('workspace.vehicles.show', $vehicle->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/vehicles/show')
                ->has('mediaSets', 1)
                ->where('mediaSets.0.selected', true)
                ->where('sites.0.public_id', $this->site->public_id)
                ->where('sites.0.has_vehicle', false)
                ->missing('mediaSets.0.id')
                ->missing('sites.0.id'));
    }

    public function test_add_to_site_creates_an_independent_copy_without_offers(): void
    {
        $catalogBefore = $this->series->fresh()?->getAttributes();
        $vehicle = WorkspaceVehicle::factory()->for($this->workspace)->forSeries($this->series)->create([
            'custom_name' => 'Kia Rio Север',
            'custom_description' => 'Городской седан',
        ]);
        $white = SeriesMediaSet::factory()->forSeries($this->series)->create();
        $black = SeriesMediaSet::factory()->forSeries($this->series)->create();
        $vehicle->selectMediaSets([$black->public_id, $white->public_id]);
        $black->update(['status' => false]);

        $this->as($this->owner)
            ->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $copy = SiteVehicle::query()->sole();
        $this->assertSame($this->site->id, $copy->site_id);
        $this->assertSame($this->series->public_id, $copy->catalog_series_public_id);
        $this->assertSame('Kia Rio Север', $copy->custom_name);
        $this->assertSame('Городской седан', $copy->custom_description);
        $this->assertSame($vehicle->id, $copy->source_workspace_vehicle_id);
        $this->assertNotSame($vehicle->public_id, $copy->public_id);
        $this->assertSame([$white->public_id], $copy->mediaSets()->pluck('series_media_sets.public_id')->all());
        $this->assertSame(0, SiteOffer::query()->count());

        $this->as($this->owner)->post(route('workspace.vehicles.copy', $vehicle->public_id), ['site' => $this->site->public_id])->assertSessionHasErrors('site');

        // Later library changes never reach the Site copy.
        $vehicle->update(['custom_name' => 'Новое имя', 'custom_description' => null]);
        $vehicle->selectMediaSets([]);
        $copy->refresh();
        $this->assertSame('Kia Rio Север', $copy->custom_name);
        $this->assertSame(1, $copy->mediaSets()->count());

        // Deleting the library entry keeps the Site vehicle; provenance becomes null.
        $this->as($this->owner)->delete(route('workspace.vehicles.destroy', $vehicle->public_id))->assertRedirect(route('workspace.vehicles.index'));
        $copy->refresh();
        $this->assertNull($copy->source_workspace_vehicle_id);
        $this->assertSame('Kia Rio Север', $copy->custom_name);

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

    public function test_save_to_library_copies_reusable_content_only_and_needs_confirmation_to_overwrite(): void
    {
        $siteVehicle = SiteVehicle::factory()->for($this->site)->forSeries($this->series)->create([
            'custom_name' => 'Rio на сайте',
            'custom_description' => 'Описание сайта',
        ]);
        $white = SeriesMediaSet::factory()->forSeries($this->series)->create();
        $siteVehicle->selectMediaSets([$white->public_id]);
        SiteOffer::factory()->for($siteVehicle, 'vehicle')->create();

        $this->as($this->owner)
            ->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Автомобиль сохранён в библиотеку.');

        $library = WorkspaceVehicle::query()->sole();
        $this->assertSame([$this->workspace->id, 'Rio на сайте', 'Описание сайта'], [$library->workspace_id, $library->custom_name, $library->custom_description]);
        $this->assertSame([$white->public_id], $library->mediaSets()->pluck('series_media_sets.public_id')->all());

        foreach (['price', 'price_minor', 'rrp_minor', 'currency', 'availability', 'badge', 'benefits'] as $column) {
            $this->assertFalse(Schema::hasColumn('workspace_vehicles', $column), $column);
        }

        $siteVehicle->update(['custom_name' => 'Обновлённый Rio']);
        $this->as($this->owner)->post(route('sites.vehicles.library', [$this->site, $siteVehicle]))->assertSessionHasErrors('library');
        $this->assertSame('Rio на сайте', $library->fresh()?->custom_name);

        $this->as($this->owner)
            ->post(route('sites.vehicles.library', [$this->site, $siteVehicle]), ['overwrite' => true])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Автомобиль в библиотеке обновлён.');
        $this->assertSame('Обновлённый Rio', $library->fresh()?->custom_name);
        $this->assertSame(1, WorkspaceVehicle::query()->count());

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
            ->patch(route('sites.vehicles.update', [$this->site, $siteVehicle]), [
                'status' => true,
                'sort_order' => 0,
                'custom_name' => 'Kia Rio Север',
                'custom_description' => 'Описание',
            ])
            ->assertSessionHasNoErrors();

        $this->as($this->owner)
            ->patch(route('sites.vehicles.update', [$this->site, $siteVehicle]), ['status' => true, 'sort_order' => 0, 'custom_name' => str_repeat('я', 121)])
            ->assertSessionHasErrors('custom_name');

        $this->assertSame('Kia Rio Север', $siteVehicle->fresh()?->custom_name);
        $this->assertSame('Kia Rio Север', app(VehicleBindings::class)->forSite($this->site)[0]['title']);
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
