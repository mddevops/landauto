<?php

namespace Database\Factories;

use App\Enums\DeliveryDestinationType;
use App\Enums\IntegrationStatus;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\SiteIntegrationBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormRoute>
 */
class FormRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'name' => 'Почта менеджера',
            'destination_type' => DeliveryDestinationType::Email,
            'email_destination' => ['manager@example.com'],
            'settings_json' => ['subject' => 'Заявка: {form.name}'],
            'status' => IntegrationStatus::Active,
        ];
    }

    public function webhook(SiteIntegrationBinding $binding): static
    {
        return $this->state([
            'name' => 'CRM',
            'destination_type' => DeliveryDestinationType::Webhook,
            'site_integration_binding_id' => $binding->id,
            'integration_profile_id' => $binding->integration_profile_id,
            'email_destination' => null,
            'settings_json' => ['method' => 'POST'],
        ]);
    }
}
