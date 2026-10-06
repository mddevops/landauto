<?php

namespace Tests\Feature\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Form;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\Submission;
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkspaceTeam;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class ExpandedRolesTest extends TestCase
{
    use BuildsWorkspaceTeam, RefreshCatalogDatabase, RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->buildTeamWorkspace();
        $this->site = Site::factory()->for($this->workspace)->create();
    }

    public function test_permission_matrix_is_exact(): void
    {
        $resolver = new WorkspacePermissionResolver;
        $keys = fn (WorkspaceRole $role): array => array_map(fn (WorkspacePermission $p): string => $p->value, $resolver->forRole($role));

        $this->assertSame(['view_site', 'view_vehicles', 'edit_prices', 'edit_benefits'], $keys(WorkspaceRole::PricingManager));
        $this->assertSame(['view_site', 'view_submissions', 'view_delivery_logs', 'retry_deliveries'], $keys(WorkspaceRole::LeadManager));
        $this->assertSame(
            ['view_site', 'view_integrations', 'manage_integrations', 'edit_form_routes', 'view_delivery_logs', 'retry_deliveries'],
            $keys(WorkspaceRole::IntegrationsManager),
        );
        $this->assertSame(['view_site', 'preview_site', 'publish_site'], $keys(WorkspaceRole::Publisher));

        $admin = $keys(WorkspaceRole::Admin);
        $this->assertContains('import_vehicles', $admin);
        foreach (['manage_workspace_vehicle_library', 'manage_billing', 'manage_roles', 'transfer_workspace_ownership'] as $denied) {
            $this->assertNotContains($denied, $admin);
        }

        foreach (WorkspaceRole::cases() as $role) {
            if ($role !== WorkspaceRole::Owner) {
                $this->assertNotContains('export_submissions', $keys($role), $role->value);
            }
        }
    }

    public function test_roles_have_russian_labels(): void
    {
        $this->assertSame('Менеджер по ценам', WorkspaceRole::PricingManager->label());
        $this->assertSame('Менеджер по заявкам', WorkspaceRole::LeadManager->label());
        $this->assertSame('Менеджер интеграций', WorkspaceRole::IntegrationsManager->label());
        $this->assertSame('Публикатор', WorkspaceRole::Publisher->label());
    }

    public function test_pricing_manager_edits_prices_but_not_design_or_publishing(): void
    {
        $user = $this->teamMember(WorkspaceRole::PricingManager)->user;
        $vehicle = SiteVehicle::factory()->for($this->site)->create();
        $offer = SiteOffer::factory()->for($vehicle, 'vehicle')->create(['price_minor' => 100_000_00]);

        $this->actingAs($user)->get(route('sites.vehicles.index', $this->site))->assertOk();
        $this->actingAs($user)->get(route('sites.vehicles.show', [$this->site, $vehicle]))->assertOk();
        $this->actingAs($user)
            ->patch(route('sites.offers.update', [$this->site, $offer]), [
                'equipment' => $offer->catalog_equipment_public_id,
                'price' => '2 000 000',
                'status' => true,
                'sort_order' => $offer->sort_order,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(2_000_000_00, $offer->refresh()->price_minor);

        $this->actingAs($user)->patch(route('sites.design.update', $this->site), [])->assertForbidden();
        $this->actingAs($user)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('can.publish', false));
        $this->actingAs($user)->post(route('sites.publishing.store', $this->site))->assertForbidden();
        $this->actingAs($user)->get(route('sites.submissions.index', $this->site))->assertForbidden();
        $this->actingAs($user)->get(route('sites.integrations.index', $this->site))->assertForbidden();
    }

    public function test_lead_manager_sees_leads_and_delivery_metadata_but_not_integrations(): void
    {
        $user = $this->teamMember(WorkspaceRole::LeadManager)->user;
        $form = Form::factory()->for($this->site)->create();
        Submission::factory()->for($form)->create();

        $this->actingAs($user)->get(route('sites.submissions.index', $this->site))->assertOk()->assertSee('79991112233');
        $this->actingAs($user)->get(route('sites.deliveries.index', $this->site))->assertOk();
        $this->actingAs($user)->get(route('sites.integrations.index', $this->site))->assertForbidden();
        $this->actingAs($user)->get(route('integrations.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('sites.design.update', $this->site), [])->assertForbidden();
        $this->actingAs($user)->post(route('sites.publishing.store', $this->site))->assertForbidden();
        $this->assertFalse(app(WorkspacePermissionResolver::class)->roleAllows(WorkspaceRole::LeadManager, WorkspacePermission::ExportSubmissions));
    }

    public function test_integrations_manager_manages_integrations_without_lead_contents(): void
    {
        $member = $this->teamMember(WorkspaceRole::IntegrationsManager);
        $member->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $other = Site::factory()->for($this->workspace)->create();
        $form = Form::factory()->for($this->site)->create();
        Submission::factory()->for($form)->create();
        $user = $member->user;

        $this->actingAs($user)->get(route('integrations.index'))->assertOk();
        $this->actingAs($user)->get(route('sites.integrations.index', $this->site))->assertOk();
        $this->actingAs($user)->get(route('sites.integrations.index', $other))->assertOk();
        $this->actingAs($user)->get(route('sites.forms.routes.index', [$this->site, $form]))->assertOk();
        $this->actingAs($user)->get(route('sites.deliveries.index', $this->site))->assertOk()->assertDontSee('79991112233');
        $this->actingAs($user)->get(route('sites.submissions.index', $this->site))->assertForbidden();
    }

    public function test_publisher_previews_and_publishes_but_cannot_edit_design_prices_or_restore(): void
    {
        $user = $this->teamMember(WorkspaceRole::Publisher)->user;
        Page::factory()->for($this->site)->home()->create();

        $this->actingAs($user)->get(route('sites.preview', $this->site))->assertOk();
        $this->actingAs($user)->get(route('sites.publishing.show', $this->site))->assertOk();
        $this->actingAs($user)->patch(route('sites.design.update', $this->site), [])->assertForbidden();
        $this->actingAs($user)->get(route('sites.vehicles.index', $this->site))->assertForbidden();
        $this->assertFalse(app(WorkspacePermissionResolver::class)->roleAllows(WorkspaceRole::Publisher, WorkspacePermission::RestoreVersion));
    }

    public function test_owner_changes_roles_and_admin_or_integrations_manager_forces_all_sites(): void
    {
        $member = $this->teamMember(WorkspaceRole::Designer);
        $member->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $member->sites()->sync([$this->site->id]);

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.role', $member->public_id), ['role' => 'publisher'])
            ->assertSessionHasNoErrors();
        $member->refresh();
        $this->assertSame(WorkspaceRole::Publisher, $member->role);
        $this->assertSame(SiteAccessMode::SelectedSites, $member->site_access_mode);

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.role', $member->public_id), ['role' => 'integrations_manager'])
            ->assertSessionHasNoErrors();
        $member->refresh();
        $this->assertSame(SiteAccessMode::AllSites, $member->site_access_mode);
        $this->assertSame(0, $member->sites()->count());

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.role', $member->public_id), ['role' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame(WorkspaceRole::Admin, $member->refresh()->role);
        $this->assertSame(SiteAccessMode::AllSites, $member->site_access_mode);
    }

    public function test_role_change_requires_manage_roles_and_never_assigns_owner(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($admin->user)
            ->put(route('workspace.team.members.role', $designer->public_id), ['role' => 'content_editor'])
            ->assertForbidden();
        $this->actingAs($admin->user)
            ->put(route('workspace.team.members.role', $designer->public_id), ['role' => 'admin'])
            ->assertForbidden();
        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.role', $designer->public_id), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertSame(WorkspaceRole::Designer, $designer->refresh()->role);
    }

    public function test_nobody_self_escalates(): void
    {
        $ownerMember = $this->workspace->members()->where('user_id', $this->owner->id)->sole();
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.role', $ownerMember->public_id), ['role' => 'admin'])
            ->assertForbidden();
        $this->actingAs($admin->user)
            ->put(route('workspace.team.members.role', $admin->public_id), ['role' => 'owner'])
            ->assertForbidden();
        $this->actingAs($designer->user)
            ->put(route('workspace.team.members.role', $designer->public_id), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(WorkspaceRole::Owner, $ownerMember->refresh()->role);
        $this->assertSame(WorkspaceRole::Admin, $admin->refresh()->role);
        $this->assertSame(WorkspaceRole::Designer, $designer->refresh()->role);
    }

    public function test_new_roles_are_invitable_and_admin_can_invite_them(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);

        foreach (['pricing_manager', 'lead_manager', 'integrations_manager', 'publisher'] as $index => $role) {
            $this->actingAs($admin->user)
                ->post(route('workspace.team.invitations.store'), ['email' => "role{$index}@example.com", 'role' => $role])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), [
                'email' => 'scoped@example.com',
                'role' => 'integrations_manager',
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->site->public_id],
            ])
            ->assertSessionHasErrors('sites');
    }
}
