<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockAuthoring;
use App\Blocks\BlockSchemaValidator;
use App\Blocks\OfficialBlockCatalog;
use App\Enums\BlockCategory;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficialBlocksTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = ['header', 'hero', 'benefits', 'cta', 'contacts', 'footer', 'vehicle-card', 'vehicle-grid', 'vehicle-gallery', 'vehicle-offers', 'vehicle-characteristics', 'vehicle-equipment'];

    public function test_catalog_contains_initial_blocks_with_valid_schemas(): void
    {
        $blocks = OfficialBlockCatalog::blocks();

        $this->assertSame(self::SLUGS, array_values(array_unique(array_column($blocks, 'slug'))));

        foreach ($blocks as $block) {
            $this->assertSame([], (new BlockSchemaValidator)->errors($block['schema']), $block['slug']);
            $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $block['name']);
        }
    }

    public function test_seeder_is_idempotent_and_never_mutates_published_versions(): void
    {
        $this->seed(OfficialBlockSeeder::class);

        $hero = BlockVersion::query()->whereRelation('definition', 'slug', 'hero')->where('version', '1.0.0')->sole();
        $publicId = $hero->definition->public_id;

        $this->seed(OfficialBlockSeeder::class);

        $this->assertSame(count(self::SLUGS), BlockDefinition::query()->platformOwned()->count());
        $this->assertSame(count(self::SLUGS), BlockDefinition::query()->count());
        $this->assertSame(0, BlockDefinition::query()->whereNotNull('developer_profile_id')->orWhereNotNull('workspace_id')->count());
        $this->assertSame(count(self::SLUGS), BlockVersion::query()->where('version', OfficialBlockCatalog::INITIAL_VERSION)->count());
        $this->assertSame(count(OfficialBlockCatalog::blocks()), BlockVersion::query()->count());
        $this->assertSame(['1.0.0', '1.1.0', '1.2.0'], BlockVersion::query()->whereRelation('definition', 'slug', 'header')->orderBy('id')->pluck('version')->all());
        $this->assertSame(['1.0.0', '1.1.0'], BlockVersion::query()->whereRelation('definition', 'slug', 'vehicle-grid')->orderBy('id')->pluck('version')->all());
        $this->assertSame($publicId, BlockDefinition::query()->where('slug', 'hero')->value('public_id'));
        $this->assertTrue(Str::isUlid($publicId));
        $this->assertSame('Первый экран', $hero->definition->name);
    }

    public function test_super_admin_rename_survives_reseeding_and_versions_stay_immutable(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $hero = BlockDefinition::query()->platformOwned()->where('slug', 'hero')->sole();
        $publicId = $hero->public_id;

        app(BlockAuthoring::class)->updateMetadata($superAdmin, $hero, 'Главный Hero', BlockCategory::Hero);
        $this->assertSame('Главный Hero', $hero->fresh()?->name);
        $versions = BlockVersion::query()->orderBy('id')->get(['id', 'block_definition_id', 'version', 'schema_json'])->toArray();

        $this->seed(OfficialBlockSeeder::class);

        $hero = BlockDefinition::query()->where('slug', 'hero')->sole();
        $this->assertSame('Главный Hero', $hero->name);
        $this->assertSame($publicId, $hero->public_id);
        $this->assertTrue($hero->isPlatformOwned());
        $this->assertSame($superAdmin->id, $hero->updated_by_user_id);
        $this->assertSame($versions, BlockVersion::query()->orderBy('id')->get(['id', 'block_definition_id', 'version', 'schema_json'])->toArray());
        $this->assertSame(count(OfficialBlockCatalog::blocks()), BlockVersion::query()->count());
        $this->assertSame('Шапка', BlockDefinition::query()->where('slug', 'header')->value('name'));
    }

    public function test_seeder_appends_missing_official_versions_without_touching_existing_metadata(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $header = BlockDefinition::query()->where('slug', 'header')->sole();
        $header->forceFill(['name' => 'Шапка (своя)'])->save();
        $header->versions()->where('version', '!=', OfficialBlockCatalog::INITIAL_VERSION)->delete();
        $initial = $header->versions()->sole()->only(['id', 'schema_json']);

        $this->seed(OfficialBlockSeeder::class);

        $header->refresh();
        $this->assertSame('Шапка (своя)', $header->name);
        $this->assertSame(['1.0.0', '1.1.0', '1.2.0'], $header->versions()->orderBy('id')->pluck('version')->all());
        $this->assertSame($initial, $header->versions()->where('version', OfficialBlockCatalog::INITIAL_VERSION)->sole()->only(['id', 'schema_json']));
    }

    public function test_official_block_can_be_placed_with_draft_state(): void
    {
        $this->seed(OfficialBlockSeeder::class);

        $benefits = BlockVersion::query()->whereRelation('definition', 'slug', 'benefits')->sole();

        $instance = BlockInstance::factory()->for($benefits, 'version')->create(['state_json' => [
            'title' => 'Почему выбирают нас',
            'columns' => 'three',
            'items' => [
                ['id' => (string) Str::ulid(), 'title' => 'Официальная гарантия', 'text' => 'Гарантия производителя.'],
                ['id' => (string) Str::ulid(), 'title' => 'Трейд-ин'],
            ],
        ]]);

        $this->assertSame('benefits', $instance->version->definition->slug);
    }
}
