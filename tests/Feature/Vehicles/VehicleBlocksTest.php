<?php

namespace Tests\Feature\Vehicles;

use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoSeries;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VehicleBlocksTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Site $site;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->user, WorkspaceRole::Designer);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->page = Page::factory()->for($this->site)->home()->create();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function vehicleBlocks(): array
    {
        return [
            'card' => ['vehicle-card'],
        ];
    }

    #[DataProvider('vehicleBlocks')]
    public function test_vehicle_block_references_only_a_vehicle_of_the_same_site(string $slug): void
    {
        $series = AutoSeries::factory()->create();
        $own = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();
        $foreign = SiteVehicle::factory()->forSeries($series)->create();

        $this->as()->post(route('sites.blocks.store', [$this->site, $this->page]), ['block' => $slug])->assertSessionHasNoErrors();
        $block = $this->page->blocks()->sole();
        $this->assertNull($block->state_json['vehicle'] ?? null, 'a new block starts without a vehicle');

        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['vehicle' => $foreign->public_id]])
            ->assertSessionHasErrors(['state.vehicle' => 'Автомобиль не найден на этом сайте.']);
        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['vehicle' => (string) $own->id]])
            ->assertSessionHasErrors('state.vehicle');

        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['vehicle' => $own->public_id]])
            ->assertSessionHasNoErrors();
        $this->assertSame($own->public_id, $block->fresh()?->state_json['vehicle']);

        $this->as()->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.0.slug', $slug)
                ->where('blocks.0.state.vehicle', $own->public_id)
                ->where('vehicles.0.public_id', $own->public_id));
    }

    public function test_vehicle_grid_shows_all_vehicles_by_default_or_a_validated_selection(): void
    {
        $series = AutoSeries::factory()->create();
        $own = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();
        $foreign = SiteVehicle::factory()->forSeries($series)->create();
        $this->as()->post(route('sites.blocks.store', [$this->site, $this->page]), ['block' => 'vehicle-grid'])->assertSessionHasNoErrors();
        $block = $this->page->blocks()->sole();
        $this->assertSame('all', $block->state_json['source']);
        $item = fn (string $vehicle): array => ['id' => (string) Str::ulid(), 'vehicle' => $vehicle, 'action' => ['type' => 'open_page', 'page' => $this->page->public_id]];
        $save = fn (array $items) => $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['source' => 'selected', 'items' => $items]]);

        $save([$item($own->public_id), $item($foreign->public_id)])
            ->assertSessionHasErrors(['state.items.1.vehicle' => 'Автомобиль не найден на этом сайте.']);
        $save([$item($own->public_id)])->assertSessionHasNoErrors();

        $this->assertSame($own->public_id, $block->fresh()?->state_json['items'][0]['vehicle']);
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
