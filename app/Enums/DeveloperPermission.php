<?php

namespace App\Enums;

/**
 * Developer creator capabilities (D-118), explicitly granted per Developer Profile. They are not
 * Workspace or platform permissions and never authorize another profile's content. Access to the
 * Developer Platform itself is the active profile, not a permission.
 */
enum DeveloperPermission: string
{
    case CreateBlocks = 'create_blocks';
    case CreateTemplates = 'create_templates';
    case SubmitMarketplaceItem = 'submit_marketplace_item';

    public function label(): string
    {
        return match ($this) {
            self::CreateBlocks => 'Создание блоков',
            self::CreateTemplates => 'Создание шаблонов',
            self::SubmitMarketplaceItem => 'Публикация в Marketplace',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::CreateBlocks => 'Блоки',
            self::CreateTemplates => 'Шаблоны',
            self::SubmitMarketplaceItem => 'Marketplace',
        };
    }

    /**
     * Granted to every new Super Admin-approved profile; stored explicitly so it can be narrowed.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return self::cases();
    }

    /**
     * Known permissions from stored keys in canonical order; unknown keys are ignored (deny).
     *
     * @param  iterable<mixed>  $keys
     * @return list<self>
     */
    public static function fromKeys(iterable $keys): array
    {
        $granted = [];

        foreach ($keys as $key) {
            $permission = is_string($key) ? self::tryFrom($key) : null;

            if ($permission !== null) {
                $granted[$permission->value] = true;
            }
        }

        return array_values(array_filter(self::cases(), fn (self $permission): bool => isset($granted[$permission->value])));
    }
}
