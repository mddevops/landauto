<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationStatus;
use App\Enums\WorkspaceRole;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class SiteIntegrationBindingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    private IntegrationProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->profile = IntegrationProfile::factory()->for($this->workspace)->create(['encrypted_credentials' => ['token' => 'binding-secret-token-9999']]);
    }

    public function test_owner_binds_a_workspace_profile_with_site_overrides(): void
    {
        $this->as($this->owner)->post(route('sites.integrations.store', $this->site), [
            'profile' => $this->profile->public_id,
            'name' => 'CRM дилера',
            'overrides' => [['key' => 'site_id', 'value' => '101'], ['key' => 'source_id', 'value' => 'landflow']],
        ])->assertSessionHasNoErrors();

        $binding = SiteIntegrationBinding::query()->sole();
        $this->assertSame([$this->site->id, $this->profile->id], [$binding->site_id, $binding->integration_profile_id]);
        $this->assertSame(['site_id' => '101', 'source_id' => 'landflow'], $binding->overrides_json);
        $this->assertStringNotContainsString('binding-secret-token-9999', (string) json_encode(DB::table('site_integration_bindings')->first()));

        $page = $this->as($this->owner)->get(route('sites.integrations.index', $this->site));
        $page->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sites/integrations')
            ->has('bindings', 1)
            ->where('bindings.0.overrides.0', ['key' => 'site_id', 'value' => '101'])
            ->where('bindings.0.profile.public_id', $this->profile->public_id)
            ->missing('bindings.0.profile.encrypted_credentials')
            ->has('profiles', 1));
        $this->assertStringNotContainsString('binding-secret-token-9999', (string) $page->getContent());
    }

    public function test_profile_from_another_workspace_cannot_be_bound(): void
    {
        $foreign = IntegrationProfile::factory()->create();

        $this->as($this->owner)->post(route('sites.integrations.store', $this->site), ['profile' => $foreign->public_id])
            ->assertSessionHasErrors('profile');

        $this->assertSame(0, SiteIntegrationBinding::query()->count());

        $binding = new SiteIntegrationBinding(['overrides_json' => []]);
        $binding->site_id = $this->site->id;
        $binding->integration_profile_id = $foreign->id;
        $this->assertThrows(fn () => $binding->save(), LogicException::class);
    }

    public function test_disabled_or_archived_profiles_cannot_be_bound(): void
    {
        $this->profile->status = IntegrationStatus::Disabled;
        $this->profile->save();

        $this->as($this->owner)->post(route('sites.integrations.store', $this->site), ['profile' => $this->profile->public_id])
            ->assertSessionHasErrors('profile');
    }

    public function test_foreign_site_and_binding_are_not_found(): void
    {
        $foreignSite = Site::factory()->create();
        $foreignBinding = SiteIntegrationBinding::factory()->for($foreignSite)->create();

        $this->as($this->owner)->get(route('sites.integrations.index', $foreignSite))->assertNotFound();
        $this->as($this->owner)->post(route('sites.integrations.store', $foreignSite), ['profile' => $this->profile->public_id])->assertNotFound();
        $this->as($this->owner)->patch(route('sites.integrations.update', [$this->site, $foreignBinding->public_id]), ['status' => 'disabled'])->assertNotFound();
        $this->as($this->owner)->delete(route('sites.integrations.destroy', [$this->site, $foreignBinding->public_id]))->assertNotFound();
        $this->assertNotNull($foreignBinding->fresh());
    }

    public function test_designer_and_content_editor_have_no_access_and_admin_manages(): void
    {
        $binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $this->profile->id]);

        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);
            $this->as($member)->get(route('sites.integrations.index', $this->site))->assertForbidden();
            $this->as($member)->post(route('sites.integrations.store', $this->site), ['profile' => $this->profile->public_id])->assertForbidden();
            $this->as($member)->patch(route('sites.integrations.update', [$this->site, $binding->public_id]), ['status' => 'disabled'])->assertForbidden();
        }

        $admin = User::factory()->create();
        $this->workspace->addMember($admin, WorkspaceRole::Admin);
        $this->as($admin)->patch(route('sites.integrations.update', [$this->site, $binding->public_id]), [
            'status' => 'disabled',
            'overrides' => [['key' => 'dealer_id', 'value' => 'D-7']],
        ])->assertSessionHasNoErrors();

        $binding->refresh();
        $this->assertSame(IntegrationStatus::Disabled, $binding->status);
        $this->assertSame(['dealer_id' => 'D-7'], $binding->overrides_json);
    }

    public function test_override_keys_and_values_are_validated(): void
    {
        $this->as($this->owner)->post(route('sites.integrations.store', $this->site), [
            'profile' => $this->profile->public_id,
            'overrides' => [['key' => 'Bad Key', 'value' => 'x'], ['key' => 'ok', 'value' => "a\nb"], ['key' => 'ok', 'value' => 'c']],
        ])->assertSessionHasErrors(['overrides.0.key', 'overrides.1.value', 'overrides.2.key']);
    }

    public function test_bound_profile_is_archived_instead_of_deleted(): void
    {
        SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $this->profile->id]);

        $this->as($this->owner)->delete(route('integrations.destroy', $this->profile->public_id))->assertRedirect();

        $this->assertSame(IntegrationStatus::Archived, $this->profile->fresh()?->status);
    }

    public function test_unused_binding_is_deleted(): void
    {
        $binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $this->profile->id]);

        $this->as($this->owner)->delete(route('sites.integrations.destroy', [$this->site, $binding->public_id]))->assertRedirect();

        $this->assertNull($binding->fresh());
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
