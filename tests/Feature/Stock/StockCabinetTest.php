<?php

namespace Tests\Feature\Stock;

use App\Enums\Entitlement;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockVehicleStatus;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Plan;
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

class StockCabinetTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private AutoSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->enableMultiPage();

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
    }

    public function test_index_filters_and_hides_foreign_workspace_items(): void
    {
        $ready = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->ready()->create([
            'color_name' => 'Белый',
            'inner_id' => 'CAB-1',
        ]);
        StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->used()->create([
            'color_name' => 'Чёрный',
            'status' => StockVehicleStatus::Draft,
        ]);
        StockVehicle::factory()->forSeries($this->series)->create([
            'workspace_id' => Workspace::factory()->create()->id,
            'color_name' => 'Чужой',
        ]);

        $this->as($this->owner)
            ->get(route('workspace.stock.index', ['condition' => 'new', 'status' => 'ready', 'q' => 'CAB']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/stock/index')
                ->has('vehicles', 1)
                ->where('vehicles.0.public_id', $ready->public_id)
                ->where('filters.condition', 'new')
                ->where('filters.status', 'ready')
                ->where('filters.q', 'CAB'));
    }

    public function test_show_update_archive_and_restore(): void
    {
        $vehicle = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->used(40_000)->ready()->create([
            'color_name' => 'Серый',
            'year' => 2020,
        ]);

        $this->as($this->owner)
            ->get(route('workspace.stock.show', $vehicle))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/stock/show')
                ->where('vehicle.public_id', $vehicle->public_id)
                ->where('vehicle.condition', StockCondition::Used->value)
                ->where('vehicle.owners_count', 1)
                ->has('vehicle.specs'));

        $this->as($this->owner)
            ->patch(route('workspace.stock.update', $vehicle), [
                'year' => 2021,
                'color_name' => 'Синий',
                'mileage_km' => 41000,
                'owners_count' => 2,
                'availability' => StockAvailability::InTransit->value,
                'price' => '999 000',
                'status' => StockVehicleStatus::Ready->value,
                'inner_id' => 'USED-9',
                'description' => 'Один хозяин',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $vehicle->refresh();
        $this->assertSame(2021, $vehicle->year);
        $this->assertSame('Синий', $vehicle->color_name);
        $this->assertSame(41000, $vehicle->mileage_km);
        $this->assertSame(2, $vehicle->owners_count);
        $this->assertSame(StockAvailability::InTransit, $vehicle->availability);
        $this->assertSame(99_900_000, $vehicle->price_minor);
        $this->assertSame('USED-9', $vehicle->inner_id);
        $this->assertSame('Один хозяин', $vehicle->description);

        $this->as($this->owner)
            ->post(route('workspace.stock.archive', $vehicle))
            ->assertRedirect(route('workspace.stock.index', ['status' => 'archived']));
        $this->assertSame(StockVehicleStatus::Archived, $vehicle->fresh()->status);

        $this->as($this->owner)
            ->post(route('workspace.stock.restore', $vehicle))
            ->assertRedirect(route('workspace.stock.show', $vehicle));
        $this->assertSame(StockVehicleStatus::Ready, $vehicle->fresh()->status);
    }

    public function test_foreign_stock_vehicle_is_not_found(): void
    {
        $foreign = StockVehicle::factory()->forSeries($this->series)->create([
            'workspace_id' => Workspace::factory()->create()->id,
        ]);

        $this->as($this->owner)->get(route('workspace.stock.show', $foreign))->assertNotFound();
        $this->as($this->owner)->patch(route('workspace.stock.update', $foreign), [
            'year' => 2020,
            'color_name' => 'X',
            'availability' => StockAvailability::InStock->value,
            'status' => StockVehicleStatus::Draft->value,
        ])->assertNotFound();
    }

    public function test_photos_can_be_uploaded_and_deleted(): void
    {
        $vehicle = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();

        $this->as($this->owner)->post(route('workspace.stock.photos.store', $vehicle), [
            'uploads' => [UploadedFile::fake()->image('a.jpg', 640, 480)],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $photo = $vehicle->photos()->sole();
        $this->assertSame(1, $vehicle->photos()->count());

        $this->as($this->owner)
            ->delete(route('workspace.stock.photos.destroy', [$vehicle, $photo]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0, $vehicle->photos()->count());
    }

    public function test_photos_can_be_reordered(): void
    {
        $vehicle = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();

        $this->as($this->owner)->post(route('workspace.stock.photos.store', $vehicle), [
            'uploads' => [
                UploadedFile::fake()->image('a.jpg', 640, 480),
                UploadedFile::fake()->image('b.jpg', 640, 480),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $ids = $vehicle->photos()->orderBy('sort_order')->orderBy('id')->pluck('public_id')->all();
        $this->assertCount(2, $ids);
        $reversed = array_reverse($ids);

        $this->as($this->owner)
            ->put(route('workspace.stock.photos.reorder', $vehicle), ['photos' => $reversed])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            $reversed,
            $vehicle->photos()->orderBy('sort_order')->orderBy('id')->pluck('public_id')->all(),
        );
    }

    private function enableMultiPage(): void
    {
        $plan = Plan::query()->create(['key' => 'stock-cab-'.uniqid(), 'name' => 'Stock', 'is_active' => true]);
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $this->workspace->forceFill(['plan_id' => $plan->id])->save();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
