<?php

namespace Database\Seeders;

use App\Blocks\OfficialBlockCatalog;
use App\Enums\SiteType;

/**
 * Official quiz Template (P9-016) for `quiz` Sites.
 */
class OfficialQuizTemplateSeeder extends OfficialFormatTemplateSeeder
{
    public const SLUG = 'quiz-car-selection';

    public const NAME = 'Квиз: подбор автомобиля';

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
        return SiteType::Quiz;
    }

    protected function blockSlug(): string
    {
        return OfficialBlockCatalog::QUIZ_SLUG;
    }

    protected function state(): array
    {
        return [
            'subtitle' => 'Ответьте на три вопроса — подберём автомобили в наличии под ваш бюджет.',
            'steps' => self::steps([
                'Какой автомобиль вы ищете?' => ['Седан', 'Кроссовер', 'Внедорожник'],
                'Какой бюджет рассматриваете?' => ['До 2 млн ₽', '2–4 млн ₽', 'Больше 4 млн ₽'],
                'Когда планируете покупку?' => ['В этом месяце', 'В ближайшие 3 месяца', 'Пока присматриваюсь'],
            ]),
            'result_text' => 'Оставьте телефон — менеджер подберёт автомобили и пришлёт цены.',
        ];
    }
}
