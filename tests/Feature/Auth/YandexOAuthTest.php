<?php

namespace Tests\Feature\Auth;

use App\Enums\AuthProvider;
use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserAuthIdentity;
use App\Support\WorkspaceEntitlements;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex.client_id' => 'test-client-id',
            'services.yandex.client_secret' => 'test-client-secret',
            'services.yandex.redirect_uri' => 'http://localhost/auth/yandex/callback',
            'services.yandex.authorize_url' => 'https://oauth.yandex.test/authorize',
            'services.yandex.token_url' => 'https://oauth.yandex.test/token',
            'services.yandex.profile_url' => 'https://login.yandex.test/info',
        ]);

        Http::preventStrayRequests();
    }

    public function test_redirect_creates_state_and_pkce_challenge(): void
    {
        $response = $this->get(route('auth.yandex.redirect'));

        $response->assertRedirect();

        $query = $this->redirectQuery($response->headers->get('Location'));

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($query['state'], session('oauth.yandex.state'));
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertNotEmpty(session('oauth.yandex.code_verifier'));
        $this->assertArrayNotHasKey('client_secret', $query);
    }

    public function test_callback_rejects_missing_or_invalid_state_without_external_requests(): void
    {
        $this->get(route('auth.yandex.callback', ['code' => 'code']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('yandex');

        $this->get(route('auth.yandex.redirect'));

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => 'wrong']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('yandex');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_oauth_state_is_one_time(): void
    {
        $state = $this->beginFlow();
        $this->fakeYandexProfile('provider-1', 'owner@example.com');

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('dashboard', absolute: false));

        auth()->logout();

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('yandex');
    }

    public function test_existing_yandex_identity_logs_in_its_user_without_changing_landflow_email(): void
    {
        $user = User::factory()->create(['email' => 'landflow@example.com']);
        $identity = new UserAuthIdentity([
            'provider' => AuthProvider::Yandex,
            'provider_user_id' => 'provider-1',
            'provider_email' => 'old@yandex.ru',
        ]);
        $identity->user()->associate($user);
        $identity->save();

        $state = $this->beginFlow();
        $this->fakeYandexProfile('provider-1', 'new@yandex.ru');

        $oldSessionId = session()->getId();

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame('landflow@example.com', $user->refresh()->email);
        $this->assertSame('new@yandex.ru', $identity->refresh()->provider_email);
    }

    public function test_new_yandex_user_is_verified_passwordless_and_owns_one_personal_workspace(): void
    {
        $state = $this->beginFlow();
        $this->fakeYandexProfile('provider-2', ' New.User@Yandex.RU ', 'Иван Петров');

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new.user@yandex.ru')->firstOrFail();
        $identity = $user->authIdentities()->sole();
        $membership = $user->memberships()->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Иван Петров', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame(AuthProvider::Yandex, $identity->provider);
        $this->assertSame('provider-2', $identity->provider_user_id);
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $this->assertSame('Иван Петров', $membership->workspace->name);
        $this->assertSame(1, $user->workspaces()->count());
        $this->assertSame(Plan::FREE_KEY, $membership->workspace->plan?->key);
        $this->assertSame(2, app(WorkspaceEntitlements::class)->limit($membership->workspace, Entitlement::MaxSites));
    }

    public function test_existing_landflow_email_is_not_logged_in_or_auto_linked(): void
    {
        $existingUser = User::factory()->create(['email' => 'member@example.com']);
        $state = $this->beginFlow();
        $this->fakeYandexProfile('new-provider', ' MEMBER@example.com ');

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, UserAuthIdentity::query()->count());
        $this->assertSame('member@example.com', $existingUser->refresh()->email);
    }

    public function test_provider_identity_unique_constraints_are_enforced(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->createIdentity($firstUser, 'provider-1');

        try {
            $this->createIdentity($secondUser, 'provider-1');
            $this->fail('A provider identity must belong to only one user.');
        } catch (QueryException) {
            $this->assertSame(1, UserAuthIdentity::query()->count());
        }

        $this->expectException(QueryException::class);
        $this->createIdentity($firstUser, 'provider-2');
    }

    public function test_yandex_profile_without_usable_email_creates_nothing(): void
    {
        $state = $this->beginFlow();

        Http::fake([
            'https://oauth.yandex.test/token' => Http::response(['access_token' => 'token']),
            'https://login.yandex.test/info*' => Http::response([
                'id' => 'provider-1',
                'client_id' => 'test-client-id',
                'default_email' => 'not-an-email',
            ]),
        ]);

        $this->get(route('auth.yandex.callback', ['code' => 'code', 'state' => $state]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('yandex');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, UserAuthIdentity::query()->count());
    }

    public function test_existing_email_password_login_still_works(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_passwordless_user_cannot_use_email_password_login_until_a_password_is_set(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'any-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function beginFlow(): string
    {
        $response = $this->get(route('auth.yandex.redirect'));
        $query = $this->redirectQuery($response->headers->get('Location'));

        return $query['state'];
    }

    private function fakeYandexProfile(string $providerUserId, string $email, string $name = 'Yandex User'): void
    {
        Http::fake([
            'https://oauth.yandex.test/token' => Http::response([
                'access_token' => 'short-lived-token',
                'token_type' => 'bearer',
            ]),
            'https://login.yandex.test/info*' => Http::response([
                'id' => $providerUserId,
                'client_id' => 'test-client-id',
                'default_email' => $email,
                'display_name' => $name,
            ]),
        ]);
    }

    private function createIdentity(User $user, string $providerUserId): void
    {
        $identity = new UserAuthIdentity([
            'provider' => AuthProvider::Yandex,
            'provider_user_id' => $providerUserId,
            'provider_email' => "$providerUserId@yandex.ru",
        ]);
        $identity->user()->associate($user);
        $identity->save();
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(?string $location): array
    {
        $this->assertNotNull($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
