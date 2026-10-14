<?php

namespace App\Enums;

/**
 * Canonical product a Marketplace Listing presents (P10-001): exactly one Block Definition or
 * one Template. The listing never copies the product or its commercial state.
 */
enum MarketplaceProductType: string
{
    case Block = 'block';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Блок',
            self::Template => 'Шаблон',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
