<?php

namespace Database\Seeders;

use App\Blocks\OfficialBlockCatalog;
use App\Enums\SiteType;

/**
 * Official chat Template (P9-017) for `chat_selection` Sites: scripted questions in a chat layout,
 * an optional pick from the Site vehicles and the lead form. No operator messages.
 */
class OfficialChatTemplateSeeder extends OfficialFormatTemplateSeeder
{
    public const SLUG = 'chat-car-selection';

    public const NAME = 'Чат: подбор автомобиля';

    protected function slug(): string
    {
        return self::SLUG;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    protected function siteType(): SiteType
    {
        return SiteType::ChatSelection;
    }

    protected function blockSlug(): string
    {
        return OfficialBlockCatalog::CHAT_SLUG;
    }

    protected function state(): array
    {
        return [
            'steps' => self::steps([
                'Для чего нужен автомобиль?' => ['Для города', 'Для семьи', 'Для путешествий'],
                'Как планируете покупку?' => ['Наличные', 'Кредит', 'Трейд-ин'],
            ]),
        ];
    }
}
