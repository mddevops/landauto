<?php

namespace Database\Seeders;

use App\Blocks\BlockStateDefaults;
use App\Blocks\OfficialBlockCatalog;
use App\Enums\SiteType;
use App\Models\BlockDefinition;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Templates\TemplatePublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Official quiz Template (P9-016) for `quiz` Sites: one Page with the quiz Block. Runs after
 * OfficialBlockSeeder; an already published Template is left untouched (versions are immutable).
 */
class OfficialQuizTemplateSeeder extends Seeder
{
    public const SLUG = 'quiz-car-selection';

    public const NAME = 'Квиз: подбор автомобиля';

    /** @var array<string, list<string>> */
    private const STEPS = [
        'Какой автомобиль вы ищете?' => ['Седан', 'Кроссовер', 'Внедорожник'],
        'Какой бюджет рассматриваете?' => ['До 2 млн ₽', '2–4 млн ₽', 'Больше 4 млн ₽'],
        'Когда планируете покупку?' => ['В этом месяце', 'В ближайшие 3 месяца', 'Пока присматриваюсь'],
    ];

    public function run(BlockStateDefaults $defaults, TemplatePublisher $publisher): void
    {
        $template = Template::query()->where('slug', self::SLUG)->first();

        if ($template?->versions()->exists()) {
            return;
        }

        $template ??= new Template(['slug' => self::SLUG]);
        $template->name = self::NAME;
        $template->forceFill(['is_official' => true, 'site_types' => [SiteType::Quiz->value]])->save();
        $template->pages()->delete();

        $home = new TemplatePage(['title' => 'Главная', 'slug' => 'home', 'sort_order' => 0]);
        $home->is_home = true;
        $home->template()->associate($template)->save();

        $version = BlockDefinition::query()->where('slug', OfficialBlockCatalog::QUIZ_SLUG)->sole()->versions()->latest('id')->firstOrFail();
        $block = new TemplateBlock(['sort_order' => 0, 'state_json' => [
            ...$defaults->fromSchema($version->schema_json),
            'subtitle' => 'Ответьте на три вопроса — подберём автомобили в наличии под ваш бюджет.',
            'steps' => array_map(fn (string $question, array $options): array => [
                'id' => (string) Str::ulid(),
                'question' => $question,
                'options' => array_map(fn (string $label): array => ['id' => (string) Str::ulid(), 'label' => $label], $options),
            ], array_keys(self::STEPS), array_values(self::STEPS)),
            'result_text' => 'Оставьте телефон — менеджер подберёт автомобили и пришлёт цены.',
        ]]);
        $block->page()->associate($home);
        $block->version()->associate($version);
        $block->save();

        $publisher->publishOfficial($template);
    }
}
