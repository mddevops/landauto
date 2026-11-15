<?php

namespace Tests\Feature\Stock;

use App\Enums\Entitlement;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockVehicleStatus;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
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

class StockWizardTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private AutoSeries $series;

    private AutoEquipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->enableMultiPage();

        $mark = AutoMark::factory()->create(['name' => 'Lada', 'url' => 'lada']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Granta', 'url' => 'granta']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create([
            'name' => 'I Рестайлинг',
            'year_from' => 2018,
            'year_to' => 2026,
        ]);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $this->equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($this->series, 'series'), 'modification')
            ->create(['name' => 'Comfort']);
    }

    public function test_create_page_requires_stock_access(): void
    {
        $this->as($this->owner)
            ->get(route('workspace.stock.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('workspaces/stock/create')->has('marks'));

        $designer = User::factory()->create();
        $this->workspace->addMember($designer, WorkspaceRole::Designer);
        $this->as($designer)->get(route('workspace.stock.create'))->assertNotFound();
    }

    public function test_owner_adds_new_stock_vehicle(): void
    {
        $ref = (string) $this->series->ref();

        $response = $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::New->value,
            'series' => $ref,
            'equipment' => $this->equipment->external_key,
            'year' => 2024,
            'body_name' => 'Седан',
            'color_name' => 'Белый',
            'color_swatch_hex' => '#FFFFFF',
            'availability' => StockAvailability::InStock->value,
            'price' => '1 250 000',
            'inner_id' => 'NEW-1',
        ]);

        $vehicle = StockVehicle::query()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('workspace.stock.show', $vehicle));
        $this->assertSame(StockCondition::New, $vehicle->condition);
        $this->assertSame(StockVehicleStatus::Ready, $vehicle->status);
        $this->assertSame($ref, $vehicle->catalog_series_ref);
        $this->assertSame($this->equipment->external_key, $vehicle->catalog_equipment_ref);
        $this->assertSame(125_000_000, $vehicle->price_minor);
        $this->assertNull($vehicle->mileage_km);
        $this->assertSame('new-1', $vehicle->publicPathKey());
    }

    public function test_owner_adds_used_stock_vehicle_with_photos_later_draft(): void
    {
        $response = $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::Used->value,
            'series' => (string) $this->series->ref(),
            'year' => 2022,
            'color_id' => '040001',
            'color_name' => 'Чёрный',
            'mileage_km' => 57575,
            'owners_count' => 2,
            'vin' => 'XTA21900051234567',
            'availability' => StockAvailability::InStock->value,
            'price' => '890000',
            'photos_later' => 1,
            'pts_document_type' => 'original_or_electronic',
            'on_warranty' => 1,
        ]);

        $vehicle = StockVehicle::query()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('workspace.stock.show', $vehicle));
        $this->assertSame(StockCondition::Used, $vehicle->condition);
        $this->assertSame(StockVehicleStatus::Draft, $vehicle->status);
        $this->assertSame(57575, $vehicle->mileage_km);
        $this->assertSame(2, $vehicle->owners_count);
        $this->assertSame('XTA21900051234567', $vehicle->vin);
        $this->assertTrue($vehicle->on_warranty);
        $this->assertSame(0, $vehicle->photos()->count());
    }

    public function test_used_vehicle_requires_mileage_and_owners_new_rejects_them(): void
    {
        $ref = (string) $this->series->ref();

        $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::Used->value,
            'series' => $ref,
            'year' => 2020,
            'color_name' => 'Серый',
            'availability' => StockAvailability::InStock->value,
        ])->assertSessionHasErrors(['mileage_km', 'owners_count']);

        $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::Used->value,
            'series' => $ref,
            'year' => 2020,
            'color_name' => 'Серый',
            'mileage_km' => 10000,
            'availability' => StockAvailability::InStock->value,
        ])->assertSessionHasErrors('owners_count');

        $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::New->value,
            'series' => $ref,
            'year' => 2024,
            'color_name' => 'Белый',
            'mileage_km' => 10,
            'owners_count' => 1,
            'availability' => StockAvailability::InStock->value,
        ])->assertSessionHasErrors(['mileage_km', 'owners_count']);
    }

    public function test_uploads_attach_to_stock_vehicle(): void
    {
        $file = UploadedFile::fake()->image('car.jpg', 800, 600);

        $this->as($this->owner)->post(route('workspace.stock.store'), [
            'condition' => StockCondition::Used->value,
            'series' => (string) $this->series->ref(),
            'year' => 2021,
            'color_name' => 'Синий',
            'mileage_km' => 12000,
            'owners_count' => 1,
            'availability' => StockAvailability::OnOrder->value,
            'uploads' => [$file],
        ])->assertSessionHasNoErrors();

        $vehicle = StockVehicle::query()->sole();
        $this->assertSame(StockVehicleStatus::Ready, $vehicle->status);
        $this->assertSame(1, $vehicle->photos()->count());
    }

    private function enableMultiPage(): void
    {
        $plan = Plan::query()->create(['key' => 'stock-wizard-'.uniqid(), 'name' => 'Stock', 'is_active' => true]);
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $this->workspace->forceFill(['plan_id' => $plan->id])->save();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
