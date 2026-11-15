<?php

namespace Tests\Feature\Stock;

use App\Enums\Entitlement;
use App\Enums\StockCondition;
use App\Enums\WorkspacePermission;
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
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class StockAccessTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private AutoSeries $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);

        $mark = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV']);
        $this->series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
    }

    public function test_admin_receives_manage_stock_and_other_roles_do_not(): void
    {
        $resolver = new WorkspacePermissionResolver;

        $this->assertContains(WorkspacePermission::ManageStock, $resolver->forRole(WorkspaceRole::Owner));
        $this->assertContains(WorkspacePermission::ManageStock, $resolver->forRole(WorkspaceRole::Admin));
        $this->assertNotContains(WorkspacePermission::ManageStock, $resolver->forRole(WorkspaceRole::Designer));
        $this->assertNotContains(WorkspacePermission::ManageStock, $resolver->forRole(WorkspaceRole::ContentEditor));
        $this->assertNotContains(WorkspacePermission::ManageStock, $resolver->forRole(WorkspaceRole::PricingManager));
    }

    public function test_stock_index_requires_multi_page_entitlement(): void
    {
        $this->grantMaxSites();

        $this->as($this->owner)->get(route('workspace.stock.index'))->assertNotFound();

        $this->enableMultiPage();

        $this->as($this->owner)
            ->get(route('workspace.stock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/stock/index')
                ->has('vehicles', 0)
                ->has('filters')
                ->has('filter_options.conditions'));
    }

    public function test_stock_nav_permission_is_hidden_without_multi_page_entitlement(): void
    {
        $this->grantMaxSites();

        $this->as($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.permissions', fn ($permissions): bool => ! in_array('manage_stock', collect($permissions)->all(), true)));

        $this->enableMultiPage();

        $this->as($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.permissions', fn ($permissions): bool => in_array('manage_stock', collect($permissions)->all(), true)));
    }

    public function test_designer_cannot_open_stock_even_with_multi_page(): void
    {
        $this->enableMultiPage();
        $designer = User::factory()->create();
        $this->workspace->addMember($designer, WorkspaceRole::Designer);

        $this->as($designer)->get(route('workspace.stock.index'))->assertNotFound();
    }

    public function test_stock_index_lists_only_current_workspace_vehicles(): void
    {
        $this->enableMultiPage();

        $own = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->create([
            'color_name' => 'Белый',
            'year' => 2024,
        ]);
        StockVehicle::factory()->forSeries($this->series)->create([
            'workspace_id' => Workspace::factory()->create()->id,
            'color_name' => 'Чёрный',
        ]);

        $this->as($this->owner)
            ->get(route('workspace.stock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/stock/index')
                ->has('vehicles', 1)
                ->where('vehicles.0.public_id', $own->public_id)
                ->where('vehicles.0.condition', StockCondition::New->value)
                ->where('vehicles.0.stock_number', $own->stock_number));
    }

    public function test_stock_number_and_public_path_key_rules(): void
    {
        $this->enableMultiPage();

        $vehicle = StockVehicle::factory()->for($this->workspace)->forSeries($this->series)->create();
        $this->assertSame(StockVehicle::STOCK_NUMBER_LENGTH, strlen($vehicle->stock_number));
        $this->assertSame($vehicle->stock_number, $vehicle->publicPathKey());

        $vehicle->inner_id = 'A-100';
        $vehicle->save();
        $this->assertSame('a-100', $vehicle->fresh()->publicPathKey());
    }

    private function grantMaxSites(): Plan
    {
        $plan = Plan::query()->create(['key' => 'stock-test-'.uniqid(), 'name' => 'Stock test', 'is_active' => true]);
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $this->workspace->forceFill(['plan_id' => $plan->id])->save();

        return $plan;
    }

    private function enableMultiPage(): void
    {
        $plan = $this->workspace->plan_id === null
            ? $this->grantMaxSites()
            : Plan::query()->findOrFail($this->workspace->plan_id);

        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $this->workspace->unsetRelation('plan');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
