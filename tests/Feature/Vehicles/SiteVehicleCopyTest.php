<?php

namespace Tests\Feature\Vehicles;

use App\Enums\BenefitType;
use App\Enums\OfferAvailability;
use App\Enums\SiteAccessMode;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteOfferBenefit;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteVehicleCopyTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $source;

    private Site $destination;

    private SiteVehicle $vehicle;

    private SeriesMediaSet $white;

    /** @var array<string, list<WorkspacePermission>> */
    private array $grants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->source = Site::factory()->for($this->workspace)->create(['name' => 'Сайт А']);
        $this->destination = Site::factory()->for($this->workspace)->create(['name' => 'Сайт Б']);

        $series = AutoSeries::factory()->create();
        $modification = AutoModification::factory()->for($series, 'series')->create();
        $comfort = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Comfort']);
        $prestige = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Prestige']);
        $this->white = SeriesMediaSet::factory()->forSeries($series)->create();

        $this->vehicle = SiteVehicle::factory()->for($this->source)->forSeries($series)->create([
            'custom_name' => 'Rio А',
            'custom_description' => 'Описание А',
            'status' => false,
        ]);
        $this->vehicle->selectMediaSets([$this->white->public_id]);

        $first = SiteOffer::factory()->for($this->vehicle, 'vehicle')->create([
            'catalog_equipment_public_id' => $comfort->public_id,
            'price_minor' => 123_456_789,
            'rrp_minor' => 130_000_001,
            'availability' => OfferAvailability::InTransit,
            'badge' => 'Хит',
            'sort_order' => 2,
        ]);
        $first->replaceBenefits([
            ['type' => BenefitType::TradeIn, 'amount_minor' => 5_000_099, 'label' => 'Трейд-ин'],
            ['type' => BenefitType::Credit, 'amount_minor' => 1_000_000],
        ]);
        SiteOffer::factory()->for($this->vehicle, 'vehicle')->create([
            'catalog_equipment_public_id' => $prestige->public_id,
            'price_minor' => 150_000_000,
            'status' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_full_copy_creates_independent_destination_rows_with_exact_money(): void
    {
        $sourceBefore = $this->snapshot($this->vehicle);

        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $this->source->public_id,
                'vehicles' => [$this->vehicle->public_id],
                'include_offers' => true,
                'include_benefits' => true,
            ])
            ->assertRedirect(route('sites.vehicles.imports.create', ['site' => $this->destination, 'source' => $this->source->public_id]))
            ->assertSessionHas('vehicle_import', [['vehicle' => $this->vehicle->public_id, 'title' => 'Rio А', 'result' => 'copied']])
            ->assertInertiaFlash('toast.message', 'Скопировано: 1 · Пропущено: 0 · Конфликтов: 0 · Ошибок: 0');

        $copy = $this->destination->vehicles()->sole();
        $this->assertNotSame($this->vehicle->public_id, $copy->public_id);
        $this->assertSame(['Rio А', 'Описание А', false, null], [$copy->custom_name, $copy->custom_description, $copy->status, $copy->source_workspace_vehicle_id]);
        $this->assertSame([$this->white->public_id], $copy->mediaSets()->pluck('series_media_sets.public_id')->all());

        $offers = $copy->offers()->ordered()->with('benefits')->get();
        $this->assertCount(2, $offers);
        $this->assertSame([150_000_000, null, false, 1], [$offers[0]->price_minor, $offers[0]->rrp_minor, $offers[0]->status, $offers[0]->sort_order]);
        $this->assertSame([123_456_789, 130_000_001, OfferAvailability::InTransit, 'Хит', 'RUB'], [$offers[1]->price_minor, $offers[1]->rrp_minor, $offers[1]->availability, $offers[1]->badge, $offers[1]->currency]);
        $this->assertSame(
            [['trade_in', 5_000_099, 'Трейд-ин'], ['credit', 1_000_000, null]],
            $offers[1]->benefits->map(fn (SiteOfferBenefit $benefit): array => [$benefit->type->value, $benefit->amount_minor, $benefit->label])->all(),
        );
        $sourceOfferIds = $this->vehicle->offers()->pluck('public_id')->all();
        $this->assertEmpty(array_intersect($sourceOfferIds, $offers->pluck('public_id')->all()));

        // The source is unchanged and later source edits never reach the copy.
        $this->assertSame($sourceBefore, $this->snapshot($this->vehicle->fresh() ?? $this->vehicle));
        $this->vehicle->offers()->update(['price_minor' => 1]);
        $this->vehicle->update(['custom_name' => 'Изменено']);
        $this->assertSame([150_000_000, 123_456_789], $copy->offers()->ordered()->pluck('price_minor')->all());
        $this->assertSame('Rio А', $copy->fresh()?->custom_name);
    }

    public function test_copy_without_commercial_data_creates_the_vehicle_only(): void
    {
        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $this->source->public_id,
                'vehicles' => [$this->vehicle->public_id],
            ])
            ->assertSessionHasNoErrors();

        $copy = $this->destination->vehicles()->sole();
        $this->assertSame(0, $copy->offers()->count());
        $this->assertSame(1, $copy->mediaSets()->count());

        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $this->source->public_id,
                'vehicles' => [$this->vehicle->public_id],
                'include_benefits' => true,
            ])
            ->assertSessionHasErrors('include_benefits');
    }

    public function test_an_existing_series_is_reported_as_a_conflict_and_left_untouched(): void
    {
        $existing = SiteVehicle::factory()->for($this->destination)->forSeries(AutoSeries::query()->where('public_id', $this->vehicle->catalog_series_public_id)->sole())->create(['custom_name' => 'Своё имя']);

        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $this->source->public_id,
                'vehicles' => [$this->vehicle->public_id, '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
                'include_offers' => true,
            ])
            ->assertSessionHas('vehicle_import', [
                ['vehicle' => $this->vehicle->public_id, 'title' => 'Rio А', 'result' => 'conflict'],
                ['vehicle' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'title' => 'Автомобиль не найден', 'result' => 'skipped'],
            ]);

        $this->assertSame(1, $this->destination->vehicles()->count());
        $this->assertSame('Своё имя', $existing->fresh()?->custom_name);
        $this->assertSame(0, $existing->offers()->count());
    }

    public function test_import_page_lists_accessible_sources_and_conflicts_without_numeric_ids(): void
    {
        Site::factory()->for(Workspace::factory())->create(['name' => 'Чужой сайт']);

        $this->as($this->owner)
            ->get(route('sites.vehicles.imports.create', ['site' => $this->destination, 'source' => $this->source->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/vehicles/import')
                ->has('sources', 1)
                ->where('sources.0.name', 'Сайт А')
                ->where('source', $this->source->public_id)
                ->where('vehicles.0.public_id', $this->vehicle->public_id)
                ->where('vehicles.0.offers_count', 2)
                ->where('vehicles.0.benefits_count', 2)
                ->where('vehicles.0.conflict', false)
                ->where('can.copyPrices', true)
                ->missing('vehicles.0.id')
                ->missing('sources.0.id'));
    }

    public function test_a_site_of_another_workspace_is_never_a_source(): void
    {
        $other = Workspace::factory()->create();
        $other->addMember($this->owner, WorkspaceRole::Owner);
        $foreign = Site::factory()->for($other)->create();
        $foreignVehicle = SiteVehicle::factory()->for($foreign)->create();

        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $foreign->public_id,
                'vehicles' => [$foreignVehicle->public_id],
            ])
            ->assertSessionHasErrors('source');

        // A vehicle ID of another Site never resolves through the chosen source either.
        $this->as($this->owner)
            ->post(route('sites.vehicles.imports.store', $this->destination), [
                'source' => $this->source->public_id,
                'vehicles' => [$foreignVehicle->public_id],
            ])
            ->assertSessionHas('vehicle_import', [['vehicle' => $foreignVehicle->public_id, 'title' => 'Автомобиль не найден', 'result' => 'skipped']]);

        $this->assertSame(0, $this->destination->vehicles()->count());
    }

    public function test_actor_needs_access_to_both_sites_and_import_rights_on_the_destination(): void
    {
        $this->grant(WorkspaceRole::Designer, [WorkspacePermission::ViewSite, WorkspacePermission::ViewVehicles, WorkspacePermission::EditVehicles, WorkspacePermission::ImportVehicles]);
        $this->grant(WorkspaceRole::ContentEditor, [WorkspacePermission::ViewSite, WorkspacePermission::ViewVehicles, WorkspacePermission::EditVehicles]);
        $payload = ['source' => $this->source->public_id, 'vehicles' => [$this->vehicle->public_id]];

        $destinationOnly = $this->member(WorkspaceRole::Designer, [$this->destination]);
        $this->as($destinationOnly->user)
            ->get(route('sites.vehicles.imports.create', $this->destination))
            ->assertInertia(fn (Assert $page) => $page->has('sources', 0));
        $this->as($destinationOnly->user)->post(route('sites.vehicles.imports.store', $this->destination), $payload)->assertSessionHasErrors('source');

        $sourceOnly = $this->member(WorkspaceRole::Designer, [$this->source]);
        $this->as($sourceOnly->user)->get(route('sites.vehicles.imports.create', $this->destination))->assertNotFound();
        $this->as($sourceOnly->user)->post(route('sites.vehicles.imports.store', $this->destination), $payload)->assertNotFound();

        $noImport = $this->member(WorkspaceRole::ContentEditor);
        $this->as($noImport->user)->get(route('sites.vehicles.imports.create', $this->destination))->assertForbidden();
        $this->as($noImport->user)->post(route('sites.vehicles.imports.store', $this->destination), $payload)->assertForbidden();

        $this->assertSame(0, $this->destination->vehicles()->count());

        $both = $this->member(WorkspaceRole::Designer, [$this->source, $this->destination]);
        $this->as($both->user)->post(route('sites.vehicles.imports.store', $this->destination), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->destination->vehicles()->count());
    }

    public function test_price_and_benefit_permissions_are_enforced_on_the_destination(): void
    {
        $base = [WorkspacePermission::ViewSite, WorkspacePermission::ViewVehicles, WorkspacePermission::EditVehicles, WorkspacePermission::ImportVehicles];
        $payload = ['source' => $this->source->public_id, 'vehicles' => [$this->vehicle->public_id]];

        $this->grant(WorkspaceRole::Designer, $base);
        $member = $this->member(WorkspaceRole::Designer);
        $this->as($member->user)->post(route('sites.vehicles.imports.store', $this->destination), [...$payload, 'include_offers' => true])->assertForbidden();
        $this->as($member->user)
            ->get(route('sites.vehicles.imports.create', $this->destination))
            ->assertInertia(fn (Assert $page) => $page->where('can.copyPrices', false)->where('can.copyBenefits', false));

        $this->grant(WorkspaceRole::Designer, [...$base, WorkspacePermission::EditPrices]);
        $this->as($member->user)->post(route('sites.vehicles.imports.store', $this->destination), [...$payload, 'include_offers' => true, 'include_benefits' => true])->assertForbidden();
        $this->assertSame(0, $this->destination->vehicles()->count());

        $this->as($member->user)->post(route('sites.vehicles.imports.store', $this->destination), [...$payload, 'include_offers' => true])->assertSessionHasNoErrors();
        $copy = $this->destination->vehicles()->sole();
        $this->assertSame(2, $copy->offers()->count());
        $this->assertSame(0, SiteOfferBenefit::query()->whereIn('site_offer_id', $copy->offers()->pluck('id'))->count());
    }

    /**
     * @param  list<WorkspacePermission>  $permissions
     */
    private function grant(WorkspaceRole $role, array $permissions): void
    {
        $this->grants[$role->value] = $permissions;

        $this->app->instance(WorkspacePermissionResolver::class, new class($this->grants) extends WorkspacePermissionResolver
        {
            /**
             * @param  array<string, list<WorkspacePermission>>  $grants
             */
            public function __construct(private array $grants) {}

            public function forRole(WorkspaceRole $role): array
            {
                return $this->grants[$role->value] ?? parent::forRole($role);
            }
        });
        $this->app->forgetScopedInstances();
    }

    /**
     * @param  list<Site>|null  $sites  null keeps all_sites
     */
    private function member(WorkspaceRole $role, ?array $sites = null): WorkspaceMember
    {
        $member = $this->workspace->addMember(User::factory()->create(), $role);

        if ($sites !== null) {
            $member->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
            $member->sites()->attach(array_map(fn (Site $site): int => $site->id, $sites));
        }

        return $member;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(SiteVehicle $vehicle): array
    {
        return [
            'vehicle' => $vehicle->only(['public_id', 'custom_name', 'custom_description', 'status', 'sort_order']),
            'offers' => $vehicle->offers()->ordered()->with('benefits')->get()->map(fn (SiteOffer $offer): array => [
                ...$offer->only(['public_id', 'catalog_equipment_public_id', 'price_minor', 'rrp_minor', 'badge', 'status', 'sort_order']),
                'benefits' => $offer->benefits->map(fn (SiteOfferBenefit $benefit): array => [$benefit->type->value, $benefit->amount_minor, $benefit->label])->all(),
            ])->all(),
            'media' => $vehicle->mediaSets()->pluck('series_media_sets.public_id')->all(),
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
