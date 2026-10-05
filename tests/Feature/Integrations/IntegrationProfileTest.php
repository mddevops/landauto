<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProviderType;
use App\Enums\IntegrationStatus;
use App\Enums\WorkspaceRole;
use App\Models\IntegrationProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class IntegrationProfileTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_owner_creates_a_profile_with_encrypted_credentials(): void
    {
        $this->as($this->owner)->post(route('integrations.store'), $this->payload())->assertRedirect();

        $profile = IntegrationProfile::query()->sole();
        $this->assertSame($this->workspace->id, $profile->workspace_id);
        $this->assertSame(IntegrationProviderType::CustomApi, $profile->provider_type);
        $this->assertSame(IntegrationAuthType::Bearer, $profile->auth_type);
        $this->assertSame(IntegrationStatus::Active, $profile->status);
        $this->assertSame(['token' => 'fake-dealer-token-7K2F'], $profile->encrypted_credentials);

        $raw = (string) IntegrationProfile::query()->toBase()->value('encrypted_credentials');
        $this->assertStringNotContainsString('fake-dealer-token-7K2F', $raw);
        $this->assertSame(['token' => 'fake-dealer-token-7K2F'], json_decode(Crypt::decryptString($raw), true));
    }

    public function test_index_lists_only_current_workspace_profiles_without_credentials(): void
    {
        $own = IntegrationProfile::factory()->for($this->workspace)->create(['name' => 'Своя CRM']);
        IntegrationProfile::factory()->create(['name' => 'Чужая CRM']);
        IntegrationProfile::factory()->for($this->workspace)->create(['status' => IntegrationStatus::Archived]);

        $this->as($this->owner)->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('integrations/index')
                ->has('profiles', 1)
                ->where('profiles.0.public_id', $own->public_id)
                ->missing('profiles.0.encrypted_credentials')
                ->missing('profiles.0.id')
                ->missing('profiles.0.workspace_id')
                ->where('can.manage', true));
    }

    public function test_designer_and_content_editor_cannot_see_or_change_profiles(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();

        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);

            $this->as($member)->get(route('integrations.index'))->assertForbidden();
            $this->as($member)->post(route('integrations.store'), $this->payload())->assertForbidden();
            $this->as($member)->patch(route('integrations.update', $profile->public_id), $this->payload())->assertForbidden();
            $this->as($member)->delete(route('integrations.destroy', $profile->public_id))->assertForbidden();
        }

        $this->assertSame(1, IntegrationProfile::query()->count());
    }

    public function test_admin_manages_profiles(): void
    {
        $admin = User::factory()->create();
        $this->workspace->addMember($admin, WorkspaceRole::Admin);

        $this->as($admin)->get(route('integrations.index'))->assertOk();
        $this->as($admin)->post(route('integrations.store'), $this->payload())->assertRedirect();
        $this->assertSame(1, IntegrationProfile::query()->count());
    }

    public function test_foreign_workspace_profile_is_not_found(): void
    {
        $foreign = IntegrationProfile::factory()->create(['name' => 'Чужая CRM']);

        $this->as($this->owner)->patch(route('integrations.update', $foreign->public_id), [...$this->payload(), 'status' => 'active'])->assertNotFound();
        $this->as($this->owner)->delete(route('integrations.destroy', $foreign->public_id))->assertNotFound();

        $this->assertSame('Чужая CRM', $foreign->fresh()?->name);
    }

    public function test_update_changes_non_secret_fields_and_keeps_credentials(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create(['encrypted_credentials' => ['token' => 'kept-token']]);

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [
            'name' => 'Новая CRM',
            'base_url' => 'https://api.dealer.example.com/v2/leads',
            'auth_type' => 'bearer',
            'status' => 'disabled',
            'provider_type' => 'custom_api',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame('Новая CRM', $profile->name);
        $this->assertSame('https://api.dealer.example.com/v2/leads', $profile->base_url);
        $this->assertSame(IntegrationStatus::Disabled, $profile->status);
        $this->assertSame(IntegrationProviderType::Webhook, $profile->provider_type);
        $this->assertSame(['token' => 'kept-token'], $profile->encrypted_credentials);
    }

    public function test_changing_auth_type_requires_new_credentials(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [
            'name' => $profile->name,
            'base_url' => $profile->base_url,
            'auth_type' => 'basic',
            'status' => 'active',
        ])->assertSessionHasErrors(['credential_username', 'credential_password']);

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [
            'name' => $profile->name,
            'base_url' => $profile->base_url,
            'auth_type' => 'basic',
            'status' => 'active',
            'credential_username' => 'dealer',
            'credential_password' => 'pass-word',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['password' => 'pass-word', 'username' => 'dealer'], $profile->fresh()?->encrypted_credentials);
    }

    public function test_validation_rejects_invalid_input(): void
    {
        $this->as($this->owner)->post(route('integrations.store'), [
            ...$this->payload(),
            'base_url' => 'ftp://crm.example.com',
            'auth_type' => 'api_key_header',
            'api_key_header' => 'Authorization',
            'credential_token' => "line\nbreak",
        ])->assertSessionHasErrors(['base_url', 'api_key_header', 'credential_token']);

        $this->as($this->owner)->post(route('integrations.store'), [...$this->payload(), 'credential_token' => ''])
            ->assertSessionHasErrors(['credential_token']);

        $this->assertSame(0, IntegrationProfile::query()->count());
    }

    public function test_unused_profile_is_deleted(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();

        $this->as($this->owner)->delete(route('integrations.destroy', $profile->public_id))->assertRedirect();

        $this->assertNull($profile->fresh());
    }

    public function test_owner_and_provider_type_are_immutable(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();

        $profile->provider_type = IntegrationProviderType::CustomApi;
        $this->assertThrows(fn () => $profile->save(), LogicException::class);
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'name' => 'Dealer API Test',
            'provider_type' => 'custom_api',
            'base_url' => 'https://api.dealer.example.com/leads',
            'auth_type' => 'bearer',
            'credential_token' => 'fake-dealer-token-7K2F',
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
