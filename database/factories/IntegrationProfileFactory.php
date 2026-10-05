<?php

namespace Database\Factories;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProviderType;
use App\Enums\IntegrationStatus;
use App\Models\IntegrationProfile;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationProfile>
 */
class IntegrationProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'CRM '.fake()->unique()->numberBetween(1, 99999),
            'provider_type' => IntegrationProviderType::Webhook,
            'base_url' => 'https://crm.example.com/leads',
            'auth_type' => IntegrationAuthType::Bearer,
            'encrypted_credentials' => ['token' => 'factory-token-0000'],
            'credentials_hint' => '0000',
            'status' => IntegrationStatus::Active,
        ];
    }

    public function withoutAuth(): static
    {
        return $this->state(['auth_type' => IntegrationAuthType::None, 'encrypted_credentials' => null, 'credentials_hint' => null]);
    }
}
