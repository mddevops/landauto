<?php

namespace App\Enums;

/**
 * Site format fixed at creation (D-119). It is a Site domain field, not a kind of Template.
 */
enum SiteType: string
{
    case MultiPage = 'multi_page';
    case Landing = 'landing';
    case Quiz = 'quiz';
    case ChatSelection = 'chat_selection';

    public function label(): string
    {
        return match ($this) {
            self::MultiPage => 'Многостраничный сайт',
            self::Landing => 'Лендинг',
            self::Quiz => 'Квиз',
            self::ChatSelection => 'Чат-подбор',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MultiPage => 'Несколько страниц с общим меню: каталог, услуги, контакты.',
            self::Landing => 'Одна страница с блоками для конкретного предложения.',
            self::Quiz => 'Пошаговые вопросы и заявка в конце. Создаётся из шаблона.',
            self::ChatSelection => 'Подбор автомобиля в формате переписки и заявка в конце. Создаётся из шаблона.',
        };
    }

    /**
     * Typed capability required to create this format; null means every plan may create it.
     */
    public function requiredEntitlement(): ?Entitlement
    {
        return $this === self::MultiPage ? Entitlement::MultiPageSites : null;
    }

    public function allowsBlankStart(): bool
    {
        return $this === self::MultiPage || $this === self::Landing;
    }

    public function allowsPageCreation(): bool
    {
        return $this === self::MultiPage;
    }

    /**
     * Quiz and Chat Sites keep the Template structure: no Block add / remove / reorder.
     */
    public function hasLockedStructure(): bool
    {
        return $this === self::Quiz || $this === self::ChatSelection;
    }
}
