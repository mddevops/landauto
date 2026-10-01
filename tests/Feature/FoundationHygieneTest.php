<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FoundationHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_user_props_use_an_explicit_safe_allowlist(): void
    {
        $user = User::factory()->create([
            'remember_token' => 'sensitive-remember-token',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email)
                ->has('auth.user.email_verified_at')
                ->missing('auth.user.id')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.created_at')
                ->missing('auth.user.updated_at'),
        );
    }

    public function test_database_seeder_refuses_to_run_outside_local_or_testing(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder may only run in local or testing environments.');

        (new DatabaseSeeder)->run();
    }

    #[DataProvider('rateLimitedRouteProvider')]
    public function test_sensitive_auth_routes_have_working_russian_rate_limits(
        string $method,
        string $routeName,
        array $payload,
        bool $authenticated,
    ): void {
        $client = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25']);

        if ($authenticated) {
            $client = $client->actingAs(User::factory()->create());
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $client->{$method}(route($routeName), $payload);
            $this->assertNotSame(429, $response->getStatusCode());
        }

        $client->{$method}(route($routeName), $payload)
            ->assertTooManyRequests()
            ->assertSeeText(__('Too Many Requests'));
    }

    public static function rateLimitedRouteProvider(): array
    {
        return [
            'registration' => ['post', 'register.store', [], false],
            'password reset email' => ['post', 'password.email', ['email' => 'missing@example.com'], false],
            'password reset submit' => ['post', 'password.update', [], false],
            'password confirmation' => ['post', 'password.confirm.store', ['password' => 'wrong'], true],
            'account deletion' => ['delete', 'profile.destroy', ['password' => 'wrong'], true],
        ];
    }

    #[DataProvider('fortifyEmailRouteProvider')]
    public function test_fortify_email_routes_reject_array_input_without_a_server_error(
        string $routeName,
        array $payload,
    ): void {
        $this->post(route($routeName), ['email' => ['invalid'], ...$payload])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public static function fortifyEmailRouteProvider(): array
    {
        return [
            'login' => ['login.store', ['password' => 'password']],
            'registration' => ['register.store', ['name' => 'User', 'password' => 'password', 'password_confirmation' => 'password']],
            'password email' => ['password.email', []],
            'password reset' => ['password.update', ['token' => 'invalid', 'password' => 'password', 'password_confirmation' => 'password']],
        ];
    }

    public function test_every_unverified_auth_route_is_in_the_documented_allowlist(): void
    {
        $allowed = [
            'verification.notice',
            'verification.verify',
            'verification.send',
            'logout',
            'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS settings',
            'profile.edit',
            'profile.update',
            'password.confirm',
            'password.confirm.store',
            'password.confirmation',
        ];

        $actual = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => collect($route->gatherMiddleware())->contains(
                fn (string $middleware): bool => $middleware === 'auth' || str_starts_with($middleware, 'auth:'),
            ))
            ->reject(fn ($route): bool => in_array('verified', $route->gatherMiddleware(), true))
            ->map(fn ($route): string => $route->getName() ?? implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        sort($allowed);

        $this->assertSame($allowed, $actual);
    }

    public function test_unverified_user_can_submit_password_confirmation(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }
}
