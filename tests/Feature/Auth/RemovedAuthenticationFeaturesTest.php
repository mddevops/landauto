<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two-factor authentication and passkeys are not Landflow features (DECISIONS.md D-095).
 */
class RemovedAuthenticationFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_and_passkey_fortify_features_are_disabled()
    {
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
    }

    public function test_no_two_factor_passkey_or_webauthn_routes_are_registered()
    {
        $offending = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => preg_match(
                '/two-factor|two_factor|passkey|webauthn/i',
                $route->uri().' '.($route->getName() ?? ''),
            ) === 1)
            ->map(fn (RoutingRoute $route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $offending);

        foreach ([
            'two-factor.login',
            'two-factor.login.store',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.disable',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
            'passkey.login-options',
            'passkey.login',
            'passkey.confirm-options',
            'passkey.confirm',
            'passkey.registration-options',
            'passkey.store',
            'passkey.destroy',
            'well-known.passkeys',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] must not be registered.");
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function guestRemovedEndpoints(): array
    {
        return [
            'two-factor challenge page' => ['get', '/two-factor-challenge'],
            'two-factor challenge submit' => ['post', '/two-factor-challenge'],
            'passkey endpoints discovery' => ['get', '/.well-known/passkey-endpoints'],
            'passkey login options' => ['get', '/passkeys/login/options'],
            'passkey login' => ['post', '/passkeys/login'],
        ];
    }

    #[DataProvider('guestRemovedEndpoints')]
    public function test_removed_guest_endpoints_return_not_found(string $method, string $uri)
    {
        $this->{$method}($uri)->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function authenticatedRemovedEndpoints(): array
    {
        return [
            'enable two-factor' => ['post', '/user/two-factor-authentication'],
            'disable two-factor' => ['delete', '/user/two-factor-authentication'],
            'confirm two-factor' => ['post', '/user/confirmed-two-factor-authentication'],
            'two-factor QR code' => ['get', '/user/two-factor-qr-code'],
            'two-factor secret key' => ['get', '/user/two-factor-secret-key'],
            'two-factor recovery codes' => ['get', '/user/two-factor-recovery-codes'],
            'passkey confirm options' => ['get', '/passkeys/confirm/options'],
            'passkey registration options' => ['get', '/user/passkeys/options'],
            'passkey store' => ['post', '/user/passkeys'],
            'passkey destroy' => ['delete', '/user/passkeys/1'],
        ];
    }

    #[DataProvider('authenticatedRemovedEndpoints')]
    public function test_removed_authenticated_endpoints_return_not_found(string $method, string $uri)
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->{$method}($uri)
            ->assertNotFound();
    }

    public function test_two_factor_columns_and_passkeys_table_are_dropped()
    {
        $this->assertFalse(Schema::hasTable('passkeys'));

        foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column), "Column users.{$column} must not exist.");
        }
    }
}
