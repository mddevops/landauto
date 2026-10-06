<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_overview_exposes_safe_site_state_and_site_navigation(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create(['name' => 'Автосалон Юг', 'subdomain' => 'avto-yug']);

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/show')
                ->where('site.public_id', $site->public_id)
                ->where('site.name', 'Автосалон Юг')
                ->where('site.status', 'active')
                ->where('site.subdomain', 'avto-yug')
                ->where('production', null)
                ->missing('site.id')
                ->missing('site.workspace_id')
                ->where('can.update', true)
                ->where('siteContext.public_id', $site->public_id)
                ->where('siteContext.name', 'Автосалон Юг')
                ->where('siteContext.can', [
                    'preview' => true,
                    'viewVehicles' => true,
                    'viewSubmissions' => true,
                    'viewDeliveryLogs' => true,
                    'viewIntegrations' => true,
                    'editForms' => true,
                    'publish' => true,
                    'manageDomains' => true,
                    'editSeo' => true,
                ]));
    }

    public function test_workspace_pages_have_no_site_context(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Owner);

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('siteContext', null));
    }

    public function test_designer_site_navigation_hides_leads_integrations_publishing_and_domains(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Designer);
        $site = Site::factory()->for($workspace)->create();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.publish', false)
                ->where('siteContext.can', [
                    'preview' => true,
                    'viewVehicles' => false,
                    'viewSubmissions' => false,
                    'viewDeliveryLogs' => false,
                    'viewIntegrations' => false,
                    'editForms' => false,
                    'publish' => false,
                    'manageDomains' => false,
                    'editSeo' => false,
                ]));
    }

    public function test_admin_site_navigation_matches_the_admin_permission_matrix(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Admin);
        $site = Site::factory()->for($workspace)->create();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.show', $site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('siteContext.can', [
                    'preview' => true,
                    'viewVehicles' => true,
                    'viewSubmissions' => true,
                    'viewDeliveryLogs' => true,
                    'viewIntegrations' => true,
                    'editForms' => true,
                    'publish' => true,
                    'manageDomains' => true,
                    'editSeo' => true,
                ]));
    }

    public function test_foreign_site_is_not_found_and_gets_no_site_context(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Owner);
        $foreign = Site::factory()->create(['name' => 'Чужой сайт']);

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.show', $foreign))
            ->assertNotFound()
            ->assertDontSee('Чужой сайт');

        $this->patch(route('sites.update', $foreign), ['name' => 'Взлом'])->assertNotFound();
        $this->assertSame('Чужой сайт', $foreign->refresh()->name);
    }

    public function test_site_rename_requires_edit_site_settings(): void
    {
        [$owner, $workspace] = $this->member(WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create(['name' => 'Старое']);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->patch(route('sites.update', $site), ['name' => 'Новое'])
            ->assertRedirect(route('sites.show', $site))
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertSame('Новое', $site->refresh()->name);

        $this->patch(route('sites.update', $site), ['name' => ''])->assertSessionHasErrors('name');

        $designer = User::factory()->create();
        $workspace->addMember($designer, WorkspaceRole::Designer);

        $this->actingAs($designer)
            ->patch(route('sites.update', $site), ['name' => 'Дизайнерское'])
            ->assertForbidden();
        $this->assertSame('Новое', $site->refresh()->name);
    }

    /**
     * @return array{User, Workspace}
     */
    private function member(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);

        return [$user, $workspace];
    }
}
