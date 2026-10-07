<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockStudio;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlockStudioDraftTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private BlockDefinition $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->block = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'promo']);
    }

    /**
     * @param  array<string, string>  $sources
     * @return array<string, mixed>
     */
    private static function payload(int $revision = 0, array $sources = []): array
    {
        return ['revision' => $revision, 'sources' => [
            'html' => "<section>\n  <h2>{{ title }}</h2>\n</section>\n",
            'css' => "  .x { color: red; }\n",
            'js' => '',
            'schema' => '{"fields":[{"key":"title","type":"text","label":"Заголовок"}]}',
            ...$sources,
        ]];
    }

    public function test_studio_shows_a_starter_draft_without_creating_one(): void
    {
        $this->actingAs($this->profile->user)->get(route('developer.blocks.show', $this->block))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/blocks/show')
                ->where('block.category', 'other')
                ->where('block.category_label', 'Другое')
                ->where('draft.revision', 0)
                ->where('draft.saved_at', null)
                ->where('draft.checks', [])
                ->where('draft.preview', [])
                ->where('draft.sources.js', '')
                ->where('draft.sources.html', fn (string $html): bool => str_contains($html, '{{ title }}'))
                ->has('categories', 11)
                ->where('sourceMaxBytes', BlockStudio::SOURCE_MAX_BYTES)
                ->missing('draft.id')
                ->missing('draft.block_definition_id'));

        $this->assertDatabaseCount('block_drafts', 0);
    }

    public function test_saving_stores_sources_verbatim_and_never_creates_a_version(): void
    {
        $user = $this->profile->user;

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $this->block));

        $draft = BlockDraft::query()->sole();
        $this->assertSame(1, $draft->revision);
        $this->assertSame("<section>\n  <h2>{{ title }}</h2>\n</section>\n", $draft->html);
        $this->assertSame("  .x { color: red; }\n", $draft->css);
        $this->assertSame('', $draft->js);
        $this->assertSame($user->id, $draft->updated_by_user_id);

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(1, ['js' => 'landflow.resize();']))
            ->assertSessionHasNoErrors();

        $this->assertSame([2, 'landflow.resize();'], [$draft->fresh()?->revision, $draft->fresh()?->js]);
        $this->assertSame(0, BlockVersion::query()->count(), 'Draft saves never publish');

        $this->actingAs($user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('draft.revision', 2)
                ->where('draft.sources.js', 'landflow.resize();')
                ->where('draft.saved_at', fn (?string $savedAt): bool => $savedAt !== null));
    }

    public function test_stale_revision_is_rejected_without_overwriting(): void
    {
        $user = $this->profile->user;
        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(0, ['js' => 'first']));

        foreach ([0, 5] as $stale) {
            $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload($stale, ['js' => 'stale']))
                ->assertSessionHasErrors(['draft']);
        }

        $this->assertSame([1, 'first'], [BlockDraft::query()->sole()->revision, BlockDraft::query()->sole()->js]);
    }

    public function test_invalid_schema_is_saved_and_reported_with_paths(): void
    {
        $user = $this->profile->user;

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(0, ['schema' => '{"fields": [']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('draft.sources.schema', '{"fields": [')
                ->where('draft.checks', [['source' => 'schema', 'line' => null, 'path' => 'schema', 'message' => 'schema.json содержит некорректный JSON.']]));

        $schema = json_encode(['fields' => [
            ['key' => 'count', 'type' => 'number', 'label' => 'Количество', 'min' => 5, 'max' => 1],
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'fields' => [['key' => 'id', 'type' => 'text', 'label' => 'Ид']]],
        ]], JSON_THROW_ON_ERROR);

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(1, ['schema' => $schema]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page->where('draft.checks', fn ($issues): bool => collect($issues)->where('source', 'schema')->pluck('path')->sort()->values()->all() === [
                'fields.0.min',
                'fields.1.fields.0.key',
                'fields.1.max_items',
            ]));
    }

    public function test_preview_data_and_publishing_checks_round_trip(): void
    {
        $user = $this->profile->user;
        $payload = self::payload(0, [
            'html' => "<h2>{{ title }}</h2>\n{{ subtitle }}\n{{#if title}}",
            'css' => '@import "theme.css";',
        ]);
        $payload['preview'] = ['title' => 'Весенняя акция', 'items' => [['name' => 'Первый']]];

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), $payload)->assertSessionHasNoErrors();

        $this->assertSame($payload['preview'], BlockDraft::query()->sole()->preview_data);
        $this->actingAs($user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('draft.preview.title', 'Весенняя акция')
                ->where('draft.checks', [
                    ['source' => 'html', 'line' => 2, 'path' => null, 'message' => 'Поле «subtitle» не описано в схеме.'],
                    ['source' => 'html', 'line' => 3, 'path' => null, 'message' => 'Не закрыт блок {{#if …}}: добавьте {{/if}}.'],
                    ['source' => 'css', 'line' => 1, 'path' => null, 'message' => '@import запрещён в styles.css: все стили блока должны находиться в styles.css.'],
                ]));

        $tooLarge = self::payload(1);
        $tooLarge['preview'] = ['title' => str_repeat('a', BlockStudio::SOURCE_MAX_BYTES)];
        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), $tooLarge)->assertSessionHasErrors('preview');
        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), [...self::payload(1), 'preview' => 'x'])->assertSessionHasErrors('preview');
        $this->assertSame(1, BlockDraft::query()->sole()->revision);
    }

    public function test_sources_are_limited_to_64_kilobytes_each(): void
    {
        $user = $this->profile->user;
        // Cyrillic is two bytes per character, so the limit is in bytes, not characters.
        $tooLarge = str_repeat('я', intdiv(BlockStudio::SOURCE_MAX_BYTES, 2) + 1);

        foreach (['html', 'css', 'js', 'schema'] as $key) {
            $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(0, [$key => $tooLarge]))
                ->assertSessionHasErrors(["sources.{$key}"]);
        }

        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), self::payload(0, ['html' => str_repeat('a', BlockStudio::SOURCE_MAX_BYTES)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, BlockDraft::query()->count());
    }

    public function test_malformed_payloads_are_rejected(): void
    {
        $user = $this->profile->user;
        $route = route('developer.blocks.draft', $this->block);

        $this->actingAs($user)->put($route, ['sources' => self::payload()['sources']])->assertSessionHasErrors('revision');
        $this->actingAs($user)->put($route, ['revision' => 0])->assertSessionHasErrors('sources');
        $this->actingAs($user)->put($route, self::payload(0, ['php' => '<?php']))->assertSessionHasErrors('sources');
        $this->actingAs($user)->put($route, ['revision' => 0, 'sources' => ['html' => ['x'], 'css' => '', 'js' => '', 'schema' => '']])
            ->assertSessionHasErrors('sources.html');
        $this->assertDatabaseCount('block_drafts', 0);
    }

    public function test_super_admin_saves_platform_block_drafts_only(): void
    {
        $superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $official = BlockDefinition::factory()->platform()->create();

        $this->actingAs($superAdmin)->put(route('platform.blocks.draft', $official), self::payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('platform.blocks.show', $official));
        $this->assertSame($official->id, BlockDraft::query()->sole()->block_definition_id);

        $this->actingAs($superAdmin)->put(route('platform.blocks.draft', $this->block), self::payload())->assertNotFound();
        $this->actingAs($this->profile->user)->put(route('platform.blocks.draft', $official), self::payload(1))->assertForbidden();
        $this->assertSame(1, BlockDraft::query()->count());
    }
}
