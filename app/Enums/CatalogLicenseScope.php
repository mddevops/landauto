<?php

namespace App\Enums;

/**
 * Target of a catalog license (D-121): exactly one Site, or one Workspace with all its current and
 * future Sites. There is no User / account-wide scope.
 */
enum CatalogLicenseScope: string
{
    case Site = 'site';
    case Workspace = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Один сайт',
            self::Workspace => 'Всё пространство',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $scope): array => ['value' => $scope->value, 'label' => $scope->label()], self::cases());
    }
}
