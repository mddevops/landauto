<?php

namespace App\Enums;

/**
 * Generic HTTP providers of a Workspace Integration Profile. Named CRM adapters are not part of
 * the MVP (FORMS_AND_INTEGRATIONS.md §37); email delivery needs no profile.
 */
enum IntegrationProviderType: string
{
    case Webhook = 'webhook';
    case CustomApi = 'custom_api';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Вебхук',
            self::CustomApi => 'Собственный API',
        };
    }
}
