<?php

namespace Tests\Feature\Vehicles;

use App\Enums\BenefitType;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class OfferPermissionsTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private SiteVehicle $vehicle;

    private SiteOffer $offer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Автосалон Север']);
        $this->admin = User::factory()->create();
        $this->workspace->addMember($this->admin, WorkspaceRole::Admin);

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV', 'url' => 'iv']);
        $series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан', 'url' => 'sedan']);

        $this->vehicle = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();
        $this->offer = SiteOffer::factory()->for($this->vehicle, 'vehicle')->create(['price_minor' => 185_000_000]);
        $this->offer->replaceBenefits([['type' => BenefitType::TradeIn, 'amount_minor' => 10_000_000, 'label' => null]]);
    }

    public function test_admin_reaches_sites_designer_and_vehicles(): void
    {
        $this->as($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewSites', true)
                ->where('canViewVehicles', true)
                ->where('sites.0.name', 'Автосалон Север'));

        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($this->site)->save();
        $this->as($this->admin)->get(route('sites.designer', $this->site))->assertOk();

        $this->as($this->admin)->get(route('sites.vehicles.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('sites/vehicles/index')->has('vehicles', 1));

        $this->as($this->admin)->get(route('sites.vehicles.show', [$this->site, $this->vehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.editPrices', true)
                ->where('can.editBenefits', true));
    }

    public function test_admin_edits_offer_benefits(): void
    {
        $this->as($this->admin)
            ->patch(route('sites.offers.update', [$this->site, $this->offer]), $this->payload([
                'benefits' => [['type' => 'credit', 'amount' => '50 000', 'label' => 'Кредит']],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame([5_000_000], $this->offer->benefits()->pluck('amount_minor')->all());
    }

    public function test_price_permission_without_benefits_permission_changes_only_prices(): void
    {
        $this->app->instance(WorkspacePermissionResolver::class, new class extends WorkspacePermissionResolver
        {
            public function forRole(WorkspaceRole $role): array
            {
                return array_values(array_filter(
                    parent::forRole($role),
                    fn (WorkspacePermission $permission): bool => $permission !== WorkspacePermission::EditBenefits,
                ));
            }
        });

        $this->as($this->admin)->get(route('sites.vehicles.show', [$this->site, $this->vehicle]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.editPrices', true)
                ->where('can.editBenefits', false));

        $update = fn (array $overrides) => $this->as($this->admin)
            ->patch(route('sites.offers.update', [$this->site, $this->offer]), $this->payload($overrides));

        $update(['price' => '1 799 000'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(179_900_000, $this->offer->fresh()?->price_minor);

        $update(['price' => '1 799 000', 'benefits' => []])->assertForbidden();
        $update(['price' => '1 700 000', 'benefits' => []])->assertForbidden();
        $this->assertSame(179_900_000, $this->offer->fresh()?->price_minor);
        $this->assertSame(1, $this->offer->benefits()->count());

        $modification = AutoEquipment::query()->where('public_id', $this->offer->catalog_equipment_public_id)->sole()->modification;
        $this->as($this->admin)
            ->post(route('sites.offers.store', [$this->site, $this->vehicle]), $this->payload([
                'equipment' => AutoEquipment::factory()->for($modification, 'modification')->create()->public_id,
            ]))
            ->assertForbidden();
        $this->assertSame(1, SiteOffer::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'equipment' => $this->offer->catalog_equipment_public_id,
            'price' => '1 850 000',
            'rrp' => '',
            'availability' => '',
            'badge' => '',
            'status' => true,
            'sort_order' => 0,
            'benefits' => [['type' => 'trade_in', 'amount' => '100 000', 'label' => '']],
            ...$overrides,
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
