<?php

namespace Database\Seeders;

use App\Blocks\BlockStateDefaults;
use App\Enums\SiteType;
use App\Models\BlockDefinition;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Templates\TemplatePublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Official one-Page Template for a Site format (P9-016, P9-017): a home Page with one official
 * Block, published once. Runs after OfficialBlockSeeder; an already published Template is left
 * untouched (versions are immutable).
 */
abstract class OfficialFormatTemplateSeeder extends Seeder
{
    abstract protected function slug(): string;

    abstract protected function name(): string;

    abstract protected function siteType(): SiteType;

    abstract protected function blockSlug(): string;

    /**
     * Block state over the schema defaults.
     *
     * @return array<string, mixed>
     */
    abstract protected function state(): array;

    public function run(BlockStateDefaults $defaults, TemplatePublisher $publisher): void
    {
        $template = Template::query()->where('slug', $this->slug())->first();

        if ($template?->versions()->exists()) {
            return;
        }

        $template ??= new Template(['slug' => $this->slug()]);
        $template->name = $this->name();
        $template->forceFill(['is_official' => true, 'site_types' => [$this->siteType()->value]])->save();
        $template->pages()->delete();

        $home = new TemplatePage(['title' => 'Главная', 'slug' => 'home', 'sort_order' => 0]);
        $home->is_home = true;
        $home->template()->associate($template)->save();

        $version = BlockDefinition::query()->where('slug', $this->blockSlug())->sole()->versions()->latest('id')->firstOrFail();
        $block = new TemplateBlock(['sort_order' => 0, 'state_json' => [...$defaults->fromSchema($version->schema_json), ...$this->state()]]);
        $block->page()->associate($home);
        $block->version()->associate($version);
        $block->save();

        $publisher->publishOfficial($template);
    }

    /**
     * Repeater state for `steps`: question => option labels.
     *
     * @param  array<string, list<string>>  $steps
     * @return list<array{id: string, question: string, options: list<array{id: string, label: string}>}>
     */
    protected static function steps(array $steps): array
    {
        return array_map(fn (string $question, array $options): array => [
            'id' => (string) Str::ulid(),
            'question' => $question,
            'options' => array_map(fn (string $label): array => ['id' => (string) Str::ulid(), 'label' => $label], $options),
        ], array_keys($steps), array_values($steps));
    }
}
