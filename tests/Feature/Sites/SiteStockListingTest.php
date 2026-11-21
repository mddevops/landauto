<?php

namespace Tests\Feature\Sites;

use App\Automotive\StockBindings;
use App\Enums\SiteSectionType;
use App\Enums\SiteType;
use App\Enums\StockVehicleStatus;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteStockListing;
use App\Models\StockVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteStockListingTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    private AutoSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create([
            'site_type' => SiteType::MultiPage,
            'name' => 'Юг',
        ]);

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
    }

    public function test_only_cars_placed_on_this_site_are_listed_and_published(): void
    {
        $placed = $this->ready('placed-1', 100_000_000);
        $otherSite = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage]);
        $elsewhere = $this->ready('elsewhere', 90_000_000);
        SiteStockListing::factory()->create([
            'site_id' => $this->site->id,
            'stock_vehicle_id' => $placed->id,
            'price_minor' => 80_000_000,
            'currency' => 'RUB',
        ]);
        SiteStockListing::factory()->create([
            'site_id' => $otherSite->id,
            'stock_vehicle_id' => $elsewhere->id,
        ]);
        $this->ready('draft-only', 10_000_000, StockVehicleStatus::Draft);

        $this->as($this->owner)
            ->get(route('sites.stock.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/stock/index')
                ->has('listings', 1)
                ->where('listings.0.title', 'Kia Rio')
                ->where('listings.0.price_from_stock', false)
                ->missing('listings.0.id'));

        $bindings = app(StockBindings::class)->forPublication($this->site, SiteSectionType::Used);
        $this->assertCount(1, $bindings['vehicles']);
        $this->assertSame(80_000_000, $bindings['offers'][$placed->public_id]['price_minor']);
        $this->assertSame(100_000_000, $placed->fresh()->price_minor);
    }

    public function test_owner_adds_a_ready_car_and_a_site_price_does_not_change_stock(): void
    {
        $vehicle = $this->ready('add-1', 150_000_000);

        $this->as($this->owner)
            ->post(route('sites.stock.store', $this->site), ['stock_vehicle' => $vehicle->public_id])
            ->assertRedirect();

        $listing = SiteStockListing::query()->sole();
        $this->assertSame($this->site->id, $listing->site_id);
        $this->assertNull($listing->price_minor);

        $this->as($this->owner)
            ->patch(route('sites.stock.update', [$this->site, $listing]), ['price' => '1 200 000'])
            ->assertRedirect();

        $this->assertSame(120_000_000, $listing->fresh()->price_minor);
        $this->assertSame(150_000_000, $vehicle->fresh()->price_minor);

        $this->as($this->owner)
            ->delete(route('sites.stock.destroy', [$this->site, $listing]))
            ->assertRedirect(route('sites.stock.index', $this->site));

        $this->assertNull($listing->fresh());
        $this->assertNotNull($vehicle->fresh());
    }

    public function test_foreign_draft_and_duplicate_cars_are_rejected(): void
    {
        $foreign = StockVehicle::factory()->forSeries($this->series)->ready()->create([
            'workspace_id' => Workspace::factory()->create()->id,
            'condition' => 'used',
            'mileage_km' => 1000,
            'owners_count' => 1,
        ]);
        $draft = $this->ready('draft', 10_000_000, StockVehicleStatus::Draft);
        $ready = $this->ready('once', 10_000_000);
        SiteStockListing::factory()->create([
            'site_id' => $this->site->id,
            'stock_vehicle_id' => $ready->id,
        ]);

        $this->as($this->owner)
            ->post(route('sites.stock.store', $this->site), ['stock_vehicle' => $foreign->public_id])
            ->assertNotFound();
        $this->as($this->owner)
            ->post(route('sites.stock.store', $this->site), ['stock_vehicle' => $draft->public_id])
            ->assertSessionHasErrors('stock_vehicle');
        $this->as($this->owner)
            ->post(route('sites.stock.store', $this->site), ['stock_vehicle' => $ready->public_id])
            ->assertSessionHasErrors('stock_vehicle');
        $this->assertSame(1, SiteStockListing::query()->count());
    }

    public function test_site_photos_do_not_replace_stock_photos(): void
    {
        $vehicle = $this->ready('photo', 10_000_000);
        $listing = SiteStockListing::factory()->create([
            'site_id' => $this->site->id,
            'stock_vehicle_id' => $vehicle->id,
        ]);

        $this->as($this->owner)
            ->post(route('sites.stock.photos.store', [$this->site, $listing]), [
                'uploads' => [UploadedFile::fake()->image('car.jpg')],
            ])
            ->assertRedirect();

        $listing->refresh();
        $this->assertTrue($listing->photos_custom);
        $this->assertSame(1, $listing->photos()->count());
        $this->assertSame(0, $vehicle->photos()->count());

        $bindings = app(StockBindings::class)->forPublication($this->site, SiteSectionType::Used);
        $this->assertCount(1, $bindings['vehicles'][0]['media']['gallery']);
    }

    public function test_pricing_manager_can_change_price_but_not_add_a_car(): void
    {
        $manager = User::factory()->create();
        $this->workspace->addMember($manager, WorkspaceRole::PricingManager);
        $vehicle = $this->ready('price', 10_000_000);

        $this->as($manager)->get(route('sites.stock.index', $this->site))->assertOk();
        $this->as($manager)
            ->post(route('sites.stock.store', $this->site), ['stock_vehicle' => $vehicle->public_id])
            ->assertForbidden();
    }

    public function test_landing_and_foreign_site_are_hidden(): void
    {
        $landing = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::Landing]);
        $foreign = Site::factory()->create(['site_type' => SiteType::MultiPage]);

        $this->as($this->owner)->get(route('sites.stock.index', $landing))->assertNotFound();
        $this->as($this->owner)->get(route('sites.stock.index', $foreign))->assertNotFound();
    }

    private function ready(string $innerId, int $price, StockVehicleStatus $status = StockVehicleStatus::Ready): StockVehicle
    {
        return StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->used(12_000, 1)->create([
            'status' => $status,
            'inner_id' => $innerId,
            'year' => 2021,
            'color_name' => 'Серый',
            'price_minor' => $price,
        ]);
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([
            WorkspaceContext::SESSION_KEY => $this->workspace->public_id,
        ]);
    }
}
