<?php

namespace App\Enums;

enum DeliveryDestinationType: string
{
    case Email = 'email';
    case Webhook = 'webhook';
    case CustomApi = 'custom_api';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Электронная почта',
            self::Webhook => 'Вебхук',
            self::CustomApi => 'Собственный API',
        };
    }

    /**
     * HTTP destinations use a Site binding of a profile with the same provider type.
     */
    public function providerType(): ?IntegrationProviderType
    {
        return match ($this) {
            self::Email => null,
            self::Webhook => IntegrationProviderType::Webhook,
            self::CustomApi => IntegrationProviderType::CustomApi,
        };
    }
}
