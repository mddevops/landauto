<?php

namespace Tests\Feature\Integrations;

use App\Enums\WorkspaceRole;
use App\Models\IntegrationProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntegrationSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'fake-secret-token-ABCDEF-7K2F';

    private Workspace $workspace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_saved_token_is_masked_and_never_reaches_html_or_inertia_props(): void
    {
        $this->as($this->owner)->post(route('integrations.store'), $this->payload())->assertSessionHasNoErrors();

        $html = $this->as($this->owner)->get(route('integrations.index'));
        $html->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('profiles.0.credentials_mask', '••••••••7K2F')
            ->missing('profiles.0.credentials_hint'));
        $this->assertStringNotContainsString(self::TOKEN, (string) $html->getContent());
        $this->assertStringNotContainsString('ABCDEF', (string) $html->getContent());

        $json = $this->as($this->owner)->get(route('integrations.index'), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest']);
        $this->assertStringNotContainsString(self::TOKEN, (string) $json->getContent());

        $profile = IntegrationProfile::query()->sole();
        $this->assertStringNotContainsString(self::TOKEN, $profile->toJson());
        $this->assertStringNotContainsString(self::TOKEN, json_encode($profile->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode(DB::table('integration_profiles')->first(), JSON_THROW_ON_ERROR));
    }

    public function test_short_secret_gets_no_visible_hint(): void
    {
        $this->as($this->owner)->post(route('integrations.store'), [...$this->payload(), 'credential_token' => 'short-1'])->assertSessionHasNoErrors();

        $this->assertNull(IntegrationProfile::query()->sole()->credentials_hint);
        $this->as($this->owner)->get(route('integrations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('profiles.0.credentials_mask', '••••••••'));
    }

    public function test_empty_secret_input_keeps_the_stored_secret_and_a_new_one_replaces_it(): void
    {
        $this->as($this->owner)->post(route('integrations.store'), $this->payload());
        $profile = IntegrationProfile::query()->sole();
        $update = ['name' => 'Dealer API Test', 'base_url' => 'https://api.dealer.example.com/leads', 'auth_type' => 'bearer', 'status' => 'active'];

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [...$update, 'credential_token' => ''])->assertSessionHasNoErrors();
        $this->assertSame(['token' => self::TOKEN], $profile->fresh()?->encrypted_credentials);

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [...$update, 'credential_token' => 'replacement-token-XY99'])->assertSessionHasNoErrors();
        $profile->refresh();
        $this->assertSame(['token' => 'replacement-token-XY99'], $profile->encrypted_credentials);
        $this->assertSame('XY99', $profile->credentials_hint);
    }

    public function test_basic_password_can_be_replaced_without_retyping_the_username(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create(['auth_type' => 'basic', 'encrypted_credentials' => ['password' => 'old-password', 'username' => 'dealer']]);

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [
            'name' => $profile->name,
            'base_url' => $profile->base_url,
            'auth_type' => 'basic',
            'status' => 'active',
            'credential_password' => 'new-long-password-1234',
        ])->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame(['password' => 'new-long-password-1234', 'username' => 'dealer'], $profile->encrypted_credentials);
        $this->assertSame('1234', $profile->credentials_hint);
    }

    public function test_switching_to_no_auth_drops_stored_credentials(): void
    {
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();

        $this->as($this->owner)->patch(route('integrations.update', $profile->public_id), [
            'name' => $profile->name,
            'base_url' => $profile->base_url,
            'auth_type' => 'none',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertNull($profile->encrypted_credentials);
        $this->assertNull($profile->credentials_hint);
        $this->assertNull(DB::table('integration_profiles')->value('encrypted_credentials'));
    }

    public function test_validation_failure_never_flashes_or_echoes_the_secret(): void
    {
        $response = $this->as($this->owner)
            ->from(route('integrations.index'))
            ->post(route('integrations.store'), [...$this->payload(), 'base_url' => 'not-a-url']);

        $response->assertSessionHasErrors('base_url');
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode(session()->all()));

        $json = $this->as($this->owner)->postJson(route('integrations.store'), [...$this->payload(), 'base_url' => 'not-a-url']);
        $json->assertUnprocessable();
        $this->assertStringNotContainsString(self::TOKEN, (string) $json->getContent());
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
            'credential_token' => self::TOKEN,
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
