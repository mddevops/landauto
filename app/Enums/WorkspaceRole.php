<?php

namespace App\Enums;

/**
 * Workspace system roles (PERMISSIONS.md §5). Their permission catalog is defined in P1-008;
 * business logic should check permissions, not role names, except for owner semantics.
 * Phase 8 adds Pricing Manager, Lead Manager, Integrations Manager and Publisher; there are no
 * custom roles.
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Designer = 'designer';
    case ContentEditor = 'content_editor';
    case PricingManager = 'pricing_manager';
    case LeadManager = 'lead_manager';
    case IntegrationsManager = 'integrations_manager';
    case Publisher = 'publisher';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Admin => 'Администратор',
            self::Designer => 'Дизайнер',
            self::ContentEditor => 'Редактор контента',
            self::PricingManager => 'Менеджер по ценам',
            self::LeadManager => 'Менеджер по заявкам',
            self::IntegrationsManager => 'Менеджер интеграций',
            self::Publisher => 'Публикатор',
        };
    }
}
