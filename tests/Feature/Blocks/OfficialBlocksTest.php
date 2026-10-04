<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockSchemaValidator;
use App\Blocks\OfficialBlockCatalog;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficialBlocksTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = ['header', 'hero', 'benefits', 'cta', 'contacts', 'footer', 'vehicle-card', 'vehicle-grid'];

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

        $this->assertSame(count(self::SLUGS), BlockDefinition::query()->where('is_official', true)->count());
        $this->assertSame(count(self::SLUGS), BlockVersion::query()->where('version', OfficialBlockCatalog::INITIAL_VERSION)->count());
        $this->assertSame(count(OfficialBlockCatalog::blocks()), BlockVersion::query()->count());
        $this->assertSame(['1.0.0', '1.1.0', '1.2.0'], BlockVersion::query()->whereRelation('definition', 'slug', 'header')->orderBy('id')->pluck('version')->all());
        $this->assertSame($publicId, BlockDefinition::query()->where('slug', 'hero')->value('public_id'));
        $this->assertTrue(Str::isUlid($publicId));
        $this->assertSame('Первый экран', $hero->definition->name);
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
