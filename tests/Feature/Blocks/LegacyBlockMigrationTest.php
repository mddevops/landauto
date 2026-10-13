<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockAuthoringPresenter;
use App\Blocks\BlockVersionGrants;
use App\Blocks\LegacyBlockMigration;
use App\Enums\CatalogAccessMode;
use App\Enums\SiteType;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Models\Workspace;
use App\Publishing\PublishValidator;
use App\Templates\TemplatePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyBlockMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_publish_blocks_an_accessible_native_upgrade_and_upgrade_grants_atomically(): void
    {
        [$site, $instance, $legacy, $native] = $this->siteWithLegacyBlock();

        $validation = app(PublishValidator::class)->validate($site);
        $this->assertContains('legacy_block_upgrade_required', $validation->errorCodes());
        $this->assertSame([], $validation->warnings);
        $versions = app(BlockAuthoringPresenter::class)->studio($legacy->definition)['versions'];
        $this->assertIsArray($versions);
        $this->assertSame('Изолированный', array_column($versions, 'runtime_label', 'version')['1.0.0'] ?? null);

        app(LegacyBlockMigration::class)->upgradeSiteBlock($site, $instance);

        $this->assertSame($native->id, $instance->fresh()?->block_version_id);
        $this->assertTrue(app(BlockVersionGrants::class)->has($site, $native));
        $this->assertSame('1.0.0', $legacy->fresh()?->version);
        $this->assertSame('sandboxed', $legacy->fresh()->runtime->value);
        $this->assertNotContains('legacy_block_upgrade_required', app(PublishValidator::class)->validate($site)->errorCodes());
    }

    public function test_site_publish_warns_when_native_successor_is_not_accessible_and_never_grants_on_publish(): void
    {
        [$site, , , $native, $definition] = $this->siteWithLegacyBlock();
        $definition->forceFill([
            'access_mode' => CatalogAccessMode::AdminGrant,
            'site_price_minor' => null,
            'workspace_price_minor' => null,
            'price_currency' => null,
        ])->save();
        $before = DB::table('site_block_version_grants')->count();

        $validation = app(PublishValidator::class)->validate($site);

        $this->assertNotContains('legacy_block_upgrade_required', $validation->errorCodes());
        $this->assertContains('legacy_block_sandboxed', array_map(fn ($issue): string => $issue->code, $validation->warnings));
        $this->assertSame($before, DB::table('site_block_version_grants')->count());
        $this->assertFalse(app(BlockVersionGrants::class)->has($site, $native));
    }

    public function test_quiz_and_chat_sites_can_only_upgrade_through_a_template_version(): void
    {
        foreach ([SiteType::Quiz, SiteType::ChatSelection] as $type) {
            [$site, $instance] = $this->siteWithLegacyBlock($type);
            $upgrade = app(LegacyBlockMigration::class)->forSite($site, $instance);

            $this->assertFalse($upgrade->possible());
            $this->assertStringContainsString('новой версией шаблона', (string) $upgrade->reason);
            $this->assertContains('legacy_block_sandboxed', array_map(
                fn ($issue): string => $issue->code,
                app(PublishValidator::class)->validate($site)->warnings,
            ));
        }
    }

    public function test_template_version_is_blocked_only_when_a_native_successor_exists(): void
    {
        $definition = BlockDefinition::factory()->platform()->create(['name' => 'Промо']);
        $legacy = BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'schema_json' => $this->schema(),
        ]);
        $template = Template::factory()->create(['site_types' => [SiteType::Landing->value]]);
        $page = TemplatePage::factory()->for($template)->home()->create();
        $block = TemplateBlock::factory()->create([
            'template_page_id' => $page->id,
            'block_version_id' => $legacy->id,
            'state_json' => ['title' => 'Акция'],
        ]);

        $this->assertSame([], app(TemplatePublisher::class)->checks($template));

        $native = BlockVersion::factory()->native('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'version' => '1.1.0',
            'schema_json' => $this->schema(),
        ]);
        $this->assertStringContainsString('обновите его до нативной версии 1.1.0', implode(' ', app(TemplatePublisher::class)->checks($template)));

        app(LegacyBlockMigration::class)->upgradeTemplateBlock($block);
        $this->assertSame($native->id, $block->fresh()?->block_version_id);
        $this->assertSame('sandboxed', $legacy->fresh()?->runtime->value);
        $this->assertSame([], app(TemplatePublisher::class)->checks($template));
    }

    /**
     * @return array{Site, BlockInstance, BlockVersion, BlockVersion, BlockDefinition}
     */
    private function siteWithLegacyBlock(SiteType $type = SiteType::Landing): array
    {
        $site = Site::factory()->for(Workspace::factory())->create(['site_type' => $type]);
        $page = Page::factory()->for($site)->home()->create();
        $definition = BlockDefinition::factory()->platform()->create(['name' => 'Промо']);
        $legacy = BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'schema_json' => $this->schema(),
        ]);
        $native = BlockVersion::factory()->native('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'version' => '1.1.0',
            'schema_json' => $this->schema(),
        ]);
        $instance = BlockInstance::factory()->create([
            'page_id' => $page->id,
            'block_version_id' => $legacy->id,
            'state_json' => ['title' => 'Акция'],
        ]);
        app(BlockVersionGrants::class)->grant($site, [$legacy->id]);

        return [$site, $instance->setRelation('page', $page), $legacy, $native, $definition];
    }

    /**
     * @return array{fields: list<array{key: string, type: string, label: string, default: string}>}
     */
    private function schema(): array
    {
        return ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]];
    }
}
