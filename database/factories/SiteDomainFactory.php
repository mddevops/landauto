<?php

namespace Database\Factories;

use App\Enums\DomainRoutingStatus;
use App\Enums\DomainSslStatus;
use App\Enums\DomainVerificationStatus;
use App\Models\Site;
use App\Models\SiteDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDomain>
 */
class SiteDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'hostname' => fake()->unique()->domainWord().'-'.fake()->unique()->numberBetween(100, 99999).'.ru',
            'verification_token' => SiteDomain::newVerificationToken(),
        ];
    }

    public function dnsReady(): static
    {
        return $this->state(fn () => [
            'verification_status' => DomainVerificationStatus::Verified,
            'routing_status' => DomainRoutingStatus::Verified,
            'verified_at' => now(),
            'routing_verified_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->dnsReady()->state(fn () => [
            'ssl_status' => DomainSslStatus::Active,
            'ssl_issued_at' => now(),
            'ssl_expires_at' => now()->addDays(90),
        ]);
    }

    public function primary(): static
    {
        return $this->active()->state(fn () => ['is_primary' => true]);
    }
}
