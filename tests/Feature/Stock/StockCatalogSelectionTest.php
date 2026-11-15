<?php

namespace Tests\Feature\Stock;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class StockCatalogSelectionTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private AutoMark $mark;

    private AutoModel $model;

    private AutoSeries $series;

    private AutoModification $modification;

    private AutoEquipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->enableMultiPage();

        $this->mark = AutoMark::factory()->create([
            'name' => 'Lada',
            'url' => 'lada',
            'popular' => true,
        ]);
        $this->model = AutoModel::factory()->for($this->mark, 'mark')->create([
            'name' => 'Granta',
            'url' => 'granta',
            'year_from' => 2018,
            'year_to' => 2024,
        ]);
        $generation = AutoGeneration::factory()->for($this->model, 'model')->create([
            'name' => 'I Рестайлинг',
            'year_from' => 2018,
            'year_to' => 2024,
        ]);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $this->modification = AutoModification::factory()->for($this->series, 'series')->create([
            'name' => '1.6 MT',
            'engine' => 'бензин',
            'engine_volume' => '1.6',
            'engine_power' => 87,
            'transmission' => 'Механика',
            'drive' => 'Передний',
        ]);
        $this->equipment = AutoEquipment::factory()->for($this->modification, 'modification')->create([
            'name' => 'Comfort',
        ]);
    }

    public function test_cascade_requires_stock_access(): void
    {
        $designer = User::factory()->create();
        $this->workspace->addMember($designer, WorkspaceRole::Designer);

        $this->as($designer)->getJson(route('workspace.stock.catalog.marks'))->assertNotFound();

        $other = Workspace::factory()->create();
        $stranger = User::factory()->create();
        $other->addMember($stranger, WorkspaceRole::Owner);
        $this->grantPlan($other, true);

        $this->actingAs($stranger)
            ->withSession([WorkspaceContext::SESSION_KEY => $other->public_id])
            ->getJson(route('workspace.stock.catalog.marks'))
            ->assertOk();
    }

    public function test_marks_split_popular_and_all_without_numeric_ids(): void
    {
        AutoMark::factory()->create(['name' => 'Rare', 'url' => 'rare', 'popular' => false]);

        $response = $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.marks'))
            ->assertOk()
            ->assertJsonStructure(['popular', 'all']);

        $payload = $response->json();
        $this->assertContains('lada', array_column($payload['popular'], 'url'));
        $this->assertContains('lada', array_column($payload['all'], 'url'));
        $this->assertNoCatalogNumericIds($payload);
    }

    public function test_models_years_bodies_series_equipment_cascade(): void
    {
        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.models', ['mark' => 'lada']))
            ->assertOk()
            ->assertJsonPath('mark.url', 'lada')
            ->assertJsonPath('items.0.url', 'granta');

        $years = $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.years', ['mark' => 'lada', 'model' => 'granta']))
            ->assertOk()
            ->json('items');

        $this->assertContains(2018, $years);
        $this->assertContains(2024, $years);
        $this->assertSame($years, collect($years)->sortDesc()->values()->all());

        $bodies = $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.bodies', [
                'mark' => 'lada',
                'model' => 'granta',
                'year' => 2020,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame((string) $this->series->ref(), $bodies['items'][0]['ref']);
        $this->assertSame('Седан', $bodies['items'][0]['name']);
        $this->assertNoCatalogNumericIds($bodies);

        $seriesPayload = $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.series', ['series' => (string) $this->series->ref()]))
            ->assertOk()
            ->json();

        $this->assertSame((string) $this->series->ref(), $seriesPayload['series']['ref']);
        $this->assertContains('Бензин', $seriesPayload['filters']['engines']);
        $this->assertContains('Передний', $seriesPayload['filters']['drives']);
        $this->assertContains('Механика', $seriesPayload['filters']['gearboxes']);
        $this->assertNotEmpty($seriesPayload['trims']);
        $this->assertNotEmpty($seriesPayload['colors']);
        $this->assertNoCatalogNumericIds($seriesPayload);

        $modificationKey = $seriesPayload['trims'][0]['key'];

        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.equipment', [
                'series' => (string) $this->series->ref(),
                'modification' => $modificationKey,
            ]))
            ->assertOk()
            ->assertJsonPath('items.0.key', $this->equipment->external_key)
            ->assertJsonPath('items.0.name', 'Comfort');

        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.colors'))
            ->assertOk()
            ->assertJsonPath('items.0.id', '040001');
    }

    public function test_unknown_mark_model_series_return_not_found(): void
    {
        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.models', ['mark' => 'missing']))
            ->assertNotFound();

        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.years', ['mark' => 'lada', 'model' => 'missing']))
            ->assertNotFound();

        $this->as($this->owner)
            ->getJson(route('workspace.stock.catalog.series', ['series' => '1:2']))
            ->assertNotFound();
    }

    public function test_stock_without_multi_page_cannot_read_catalog_cascade(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $this->grantPlan($workspace, false);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->getJson(route('workspace.stock.catalog.marks'))
            ->assertNotFound();
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function assertNoCatalogNumericIds(array $payload): void
    {
        $forbidden = ['mark_id', 'model_id', 'generation_id', 'series_id', 'modification_id', 'equipment_id'];

        $walk = function (mixed $value, string $path) use (&$walk, $forbidden): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                if (is_string($key) && in_array($key, $forbidden, true)) {
                    $this->fail("Forbidden catalog id key [{$key}] at {$path}");
                }

                if ($key === 'id' && is_int($child)) {
                    $this->fail("Numeric catalog id at {$path}.id");
                }

                $walk($child, $path.'.'.$key);
            }
        };

        $walk($payload, 'root');
    }

    private function enableMultiPage(): void
    {
        $this->grantPlan($this->workspace, true);
    }

    private function grantPlan(Workspace $workspace, bool $multiPage): void
    {
        $plan = Plan::query()->create([
            'key' => 'stock-catalog-'.uniqid(),
            'name' => 'Stock Catalog',
            'is_active' => true,
        ]);
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $plan->setEntitlement(Entitlement::MultiPageSites, $multiPage);
        $workspace->forceFill(['plan_id' => $plan->id])->save();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
