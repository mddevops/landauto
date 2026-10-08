<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Login throttling: per email + IP and an IP-wide backstop, both read from configuration
 * (defaults 5 and 20 per minute; environments may override them, never disable them).
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_defaults_match_the_production_limits(): void
    {
        $this->assertSame(5, config('fortify.login_identity_per_minute'));
        $this->assertSame(20, config('fortify.login_ip_per_minute'));
    }

    public function test_ip_backstop_blocks_the_21st_attempt_across_distinct_emails(): void
    {
        config(['fortify.login_identity_per_minute' => 5, 'fortify.login_ip_per_minute' => 20]);

        $this->assertDistinctEmailAttemptsAllowed(20);
        $this->attempt('one-more@example.com')->assertTooManyRequests();
    }

    public function test_identity_limit_blocks_the_6th_attempt_for_the_same_email_and_ip(): void
    {
        config(['fortify.login_identity_per_minute' => 5, 'fortify.login_ip_per_minute' => 20]);
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertNotSame(429, $this->attempt($user->email)->getStatusCode());
        }

        $this->attempt($user->email)->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_configured_ip_backstop_is_respected(): void
    {
        config(['fortify.login_identity_per_minute' => 5, 'fortify.login_ip_per_minute' => 25]);

        $this->assertDistinctEmailAttemptsAllowed(25);
        $this->attempt('one-more@example.com')->assertTooManyRequests();
    }

    public function test_raised_ip_backstop_keeps_the_identity_limit(): void
    {
        config(['fortify.login_identity_per_minute' => 5, 'fortify.login_ip_per_minute' => 200]);

        $this->assertDistinctEmailAttemptsAllowed(30);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertNotSame(429, $this->attempt('same@example.com')->getStatusCode());
        }

        $this->attempt('same@example.com')->assertTooManyRequests();
    }

    public function test_configured_identity_limit_is_respected(): void
    {
        config(['fortify.login_identity_per_minute' => 3, 'fortify.login_ip_per_minute' => 20]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->assertNotSame(429, $this->attempt('same@example.com')->getStatusCode());
        }

        $this->attempt('same@example.com')->assertTooManyRequests();
        $this->assertNotSame(429, $this->attempt('other@example.com')->getStatusCode());
    }

    private function assertDistinctEmailAttemptsAllowed(int $count): void
    {
        for ($attempt = 0; $attempt < $count; $attempt++) {
            $this->assertNotSame(429, $this->attempt("unknown-{$attempt}@example.com")->getStatusCode(), "attempt {$attempt}");
        }
    }

    /**
     * @return TestResponse<Response>
     */
    private function attempt(string $email): TestResponse
    {
        return $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password']);
    }
}
