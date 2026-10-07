<?php

namespace Tests\Feature\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspaceRole;
use App\Http\Middleware\EnsureSiteAccess;
use App\Mail\WorkspaceInvitationMail;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormRoute;
use App\Models\Page;
use App\Models\Popup;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteDomain;
use App\Models\SiteIntegrationBinding;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkspaceTeam;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteAccessTest extends TestCase
{
    use BuildsWorkspaceTeam, RefreshCatalogDatabase, RefreshDatabase;

    private Site $siteA;

    private Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->buildTeamWorkspace();
        $this->siteA = Site::factory()->for($this->workspace)->create(['name' => 'Сайт А']);
        $this->siteB = Site::factory()->for($this->workspace)->create(['name' => 'Сайт Б']);
    }

    public function test_existing_members_keep_access_to_all_sites(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->assertSame(SiteAccessMode::AllSites, $designer->refresh()->site_access_mode);
        $this->actingAs($designer->user)->get(route('sites.show', $this->siteA))->assertOk();
        $this->actingAs($designer->user)->get(route('sites.show', $this->siteB))->assertOk();
        $this->actingAs($designer->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('sites', 2));
    }

    public function test_owner_and_admin_are_forced_to_all_sites(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $admin->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $ownerMember = $this->workspace->members()->where('user_id', $this->owner->id)->sole();
        $ownerMember->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();

        $this->actingAs($admin->user)->get(route('sites.show', $this->siteB))->assertOk();
        $this->actingAs($this->owner)->get(route('sites.show', $this->siteB))->assertOk();

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.site-access', $admin->public_id), [
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->siteA->public_id],
            ])
            ->assertSessionHasErrors(['sites' => 'Для этой роли всегда открыт доступ ко всем сайтам.']);
        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), [
                'email' => 'admin@example.com',
                'role' => 'admin',
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->siteA->public_id],
            ])
            ->assertSessionHasErrors('sites');
    }

    public function test_selected_sites_member_sees_only_assigned_sites(): void
    {
        $designer = $this->selectedDesigner([$this->siteA]);

        $this->actingAs($designer->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sites', 1)
                ->where('sites.0.public_id', $this->siteA->public_id));
        $this->actingAs($designer->user)->get(route('sites.show', $this->siteA))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('siteContext.public_id', $this->siteA->public_id));
        $this->actingAs($designer->user)->get(route('sites.show', $this->siteB))->assertNotFound();
        $this->actingAs($designer->user)->get(route('sites.designer', $this->siteB))->assertNotFound();
    }

    public function test_direct_nested_urls_of_unassigned_site_return_404(): void
    {
        $designer = $this->selectedDesigner([$this->siteA]);
        $page = Page::factory()->for($this->siteB)->create();
        $form = Form::factory()->for($this->siteB)->create();
        $popup = Popup::factory()->for($this->siteB)->create();
        $asset = SiteAsset::factory()->for($this->siteB)->create();

        $user = $designer->user;
        $this->actingAs($user)->patch(route('sites.pages.update', [$this->siteB, $page]), ['title' => 'Взлом'])->assertNotFound();
        $this->actingAs($user)->get(route('sites.forms.show', [$this->siteB, $form]))->assertNotFound();
        $this->actingAs($user)->patch(route('sites.popups.update', [$this->siteB, $popup]), [])->assertNotFound();
        $this->actingAs($user)->get(route('sites.assets.show', [$this->siteB, $asset]))->assertNotFound();
        $this->actingAs($user)->post(route('sites.pages.store', $this->siteB), ['title' => 'Новая'])->assertNotFound();

        $this->assertNotSame('Взлом', $page->refresh()->title);
        $this->assertSame(0, Page::query()->where('site_id', $this->siteB->id)->where('title', 'Новая')->count());
    }

    public function test_every_site_route_is_guarded_by_site_access(): void
    {
        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'sites/{site}'))
            ->reject(fn (RoutingRoute $route): bool => in_array(EnsureSiteAccess::class, $route->gatherMiddleware(), true))
            ->map(fn (RoutingRoute $route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unguarded);
    }

    public function test_selected_member_cannot_reach_any_site_route_of_an_unassigned_site(): void
    {
        $owner = $this->owner;
        $designer = $this->selectedDesigner([$this->siteA]);
        $resources = $this->siteBResources();
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'sites/{site}')) {
                continue;
            }

            $parameters = ['site' => $this->siteB->public_id];
            foreach ($route->parameterNames() as $name) {
                if ($name !== 'site') {
                    $parameters[$name] = $resources[$name] ?? strtolower((string) Str::ulid());
                }
            }

            $method = collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();
            $uri = route((string) $route->getName(), $parameters);

            $this->actingAs($designer->user)->call($method, $uri)->assertNotFound();
            $checked++;
        }

        $this->assertGreaterThanOrEqual(68, $checked);
        $this->actingAs($owner)->get(route('sites.show', $this->siteB))->assertOk();
    }

    public function test_foreign_workspace_site_is_still_404_and_foreign_site_ids_are_rejected(): void
    {
        $foreignSite = Site::factory()->for(Workspace::factory())->create();
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($designer->user)->get(route('sites.show', $foreignSite))->assertNotFound();
        $this->actingAs($this->owner)->get(route('sites.show', $foreignSite))->assertNotFound();

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.site-access', $designer->public_id), [
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->siteA->public_id, $foreignSite->public_id],
            ])
            ->assertSessionHasErrors(['sites' => 'Некоторые выбранные сайты недоступны.']);
        $this->assertSame(SiteAccessMode::AllSites, $designer->refresh()->site_access_mode);
        $this->assertSame(0, $designer->sites()->count());

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), [
                'email' => 'x@example.com',
                'role' => 'designer',
                'site_access_mode' => 'selected_sites',
                'sites' => [$foreignSite->public_id],
            ])
            ->assertSessionHasErrors('sites');
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_selected_sites_requires_at_least_one_site(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.site-access', $designer->public_id), ['site_access_mode' => 'selected_sites', 'sites' => []])
            ->assertSessionHasErrors(['sites' => 'Выберите хотя бы один сайт.']);
    }

    public function test_switching_modes_takes_effect_immediately(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $user = $designer->user;

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.site-access', $designer->public_id), [
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->siteA->public_id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workspace.team.index'));
        $this->actingAs($user)->get(route('sites.show', $this->siteB))->assertNotFound();
        $this->actingAs($user)->get(route('sites.show', $this->siteA))->assertOk();

        $this->actingAs($this->owner)
            ->put(route('workspace.team.members.site-access', $designer->public_id), ['site_access_mode' => 'all_sites'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $designer->sites()->count());
        $this->actingAs($user)->get(route('sites.show', $this->siteB))->assertOk();
    }

    public function test_admin_cannot_change_access_of_admins_and_designers_cannot_change_access(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $otherAdmin = $this->teamMember(WorkspaceRole::Admin);
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $payload = ['site_access_mode' => 'selected_sites', 'sites' => [$this->siteA->public_id]];

        $this->actingAs($admin->user)->put(route('workspace.team.members.site-access', $otherAdmin->public_id), $payload)->assertForbidden();
        $this->actingAs($designer->user)->put(route('workspace.team.members.site-access', $admin->public_id), $payload)->assertForbidden();
        $this->actingAs($admin->user)->put(route('workspace.team.members.site-access', $designer->public_id), $payload)->assertSessionHasNoErrors();

        $this->assertSame(SiteAccessMode::SelectedSites, $designer->refresh()->site_access_mode);
    }

    public function test_invitation_with_selected_sites_becomes_member_site_access(): void
    {
        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), [
                'email' => 'scoped@example.com',
                'role' => 'content_editor',
                'site_access_mode' => 'selected_sites',
                'sites' => [$this->siteB->public_id],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route('workspace.team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.site_access_mode', 'selected_sites')
                ->where('invitations.0.site_count', 1));

        $url = '';
        Mail::assertSent(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });
        $invitee = User::factory()->create(['email' => 'scoped@example.com']);
        $this->actingAs($invitee)->get($url);
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertRedirect(route('dashboard'));

        $member = $this->workspace->members()->where('user_id', $invitee->id)->sole();
        $this->assertSame(SiteAccessMode::SelectedSites, $member->site_access_mode);
        $this->assertSame([$this->siteB->id], $member->sites()->pluck('sites.id')->all());
        $this->actingAs($invitee)->get(route('sites.show', $this->siteA))->assertNotFound();
        $this->actingAs($invitee)->get(route('sites.show', $this->siteB))->assertOk();
    }

    public function test_removing_a_member_deletes_site_access_rows(): void
    {
        $designer = $this->selectedDesigner([$this->siteA, $this->siteB]);

        $this->actingAs($this->owner)->delete(route('workspace.team.members.destroy', $designer->public_id));

        $this->assertDatabaseMissing('site_member_access', ['workspace_member_id' => $designer->id]);
    }

    public function test_team_page_exposes_site_public_ids_only(): void
    {
        $this->selectedDesigner([$this->siteA]);

        $response = $this->actingAs($this->owner)->get(route('workspace.team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sites', 2)
                ->where('members.1.site_access_mode', 'selected_sites')
                ->where('members.1.sites', [$this->siteA->public_id])
                ->where('allSitesRoles', ['owner', 'admin', 'integrations_manager']));

        $this->assertStringNotContainsString('"site_id"', (string) $response->getContent());
    }

    /**
     * @param  list<Site>  $sites
     */
    private function selectedDesigner(array $sites): WorkspaceMember
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $designer->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $designer->sites()->sync(array_map(fn (Site $site): int => $site->id, $sites));

        return $designer;
    }

    /**
     * Real nested resources of Site B keyed by route parameter name.
     *
     * @return array<string, string>
     */
    private function siteBResources(): array
    {
        $page = Page::factory()->for($this->siteB)->create();
        $form = Form::factory()->for($this->siteB)->create();
        $vehicle = SiteVehicle::factory()->for($this->siteB)->create();

        return [
            'page' => (string) $page->getRouteKey(),
            'block' => (string) BlockInstance::factory()->for($page)->create()->getRouteKey(),
            'form' => (string) $form->getRouteKey(),
            'field' => (string) FormField::factory()->for($form)->create()->getRouteKey(),
            'route' => (string) FormRoute::factory()->for($form)->create()->getRouteKey(),
            'popup' => (string) Popup::factory()->for($this->siteB)->create()->getRouteKey(),
            'asset' => (string) SiteAsset::factory()->for($this->siteB)->create()->getRouteKey(),
            'vehicle' => (string) $vehicle->getRouteKey(),
            'offer' => (string) SiteOffer::factory()->for($vehicle, 'vehicle')->create()->getRouteKey(),
            'domain' => (string) SiteDomain::factory()->for($this->siteB)->create()->getRouteKey(),
            'binding' => (string) SiteIntegrationBinding::factory()->for($this->siteB)->create()->getRouteKey(),
            'version' => (string) PublishedVersion::factory()->for($this->siteB)->create()->getRouteKey(),
        ];
    }
}
