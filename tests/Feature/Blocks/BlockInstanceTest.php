<?php

namespace Tests\Feature\Blocks;

use App\Exceptions\InvalidBlockStateException;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class BlockInstanceTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEMA = ['fields' => [
        ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'max_length' => 40],
    ]];

    public function test_instance_is_owned_by_site_page_and_pins_block_version(): void
    {
        $site = Site::factory()->create();
        $page = Page::factory()->for($site)->home()->create();
        $version = BlockVersion::factory()->create(['schema_json' => self::SCHEMA]);
        BlockVersion::factory()->for($version->definition, 'definition')->create([
            'version' => '2.0.0',
            'schema_json' => self::SCHEMA,
        ]);

        $instance = BlockInstance::factory()->for($page)->for($version, 'version')->create([
            'state_json' => ['title' => 'Новые автомобили'],
        ]);
        $second = BlockInstance::factory()->for($page)->for($version, 'version')->create(['sort_order' => 1]);

        $this->assertTrue($instance->page->site->is($site));
        $this->assertTrue($instance->version->is($version));
        $this->assertSame('1.0.0', $instance->version->version);
        $this->assertSame([$instance->id, $second->id], $page->blocks()->pluck('id')->all());
        $this->assertSame(['title' => 'Новые автомобили'], $instance->fresh()?->state_json);
        $this->assertTrue(Str::isUlid($instance->public_id));
        $this->assertSame([], array_intersect(['id', 'page_id', 'block_version_id'], array_keys($instance->toArray())));
    }

    public function test_state_is_validated_against_pinned_schema_on_create_and_update(): void
    {
        $version = BlockVersion::factory()->create(['schema_json' => self::SCHEMA]);

        try {
            BlockInstance::factory()->for($version, 'version')->create(['state_json' => ['api_key' => 'secret']]);
            $this->fail('Unknown state keys must be rejected.');
        } catch (InvalidBlockStateException $exception) {
            $this->assertArrayHasKey('state.api_key', $exception->errors);
        }

        $this->assertSame(0, BlockInstance::query()->count());

        $instance = BlockInstance::factory()->for($version, 'version')->create(['state_json' => ['title' => 'Акция']]);

        $this->expectException(InvalidBlockStateException::class);

        $instance->update(['state_json' => ['title' => str_repeat('я', 41)]]);
    }

    public function test_page_and_pinned_version_are_immutable(): void
    {
        $version = BlockVersion::factory()->create(['schema_json' => self::SCHEMA]);
        $newer = BlockVersion::factory()->for($version->definition, 'definition')->create([
            'version' => '1.1.0',
            'schema_json' => self::SCHEMA,
        ]);
        $instance = BlockInstance::factory()->for($version, 'version')->create();

        try {
            $instance->forceFill(['block_version_id' => $newer->id])->save();
            $this->fail('Block Version must stay pinned.');
        } catch (LogicException) {
            $this->assertSame($version->id, $instance->fresh()?->block_version_id);
        }

        $this->expectException(LogicException::class);

        $instance->refresh()->forceFill(['page_id' => Page::factory()->create()->id])->save();
    }

    public function test_only_platform_owned_blocks_can_be_placed(): void
    {
        foreach ([BlockDefinition::factory()->developer(), BlockDefinition::factory()->workspacePrivate()] as $factory) {
            $version = BlockVersion::factory()->for($factory->create(), 'definition')->create();

            try {
                BlockInstance::factory()->for($version, 'version')->create();
                $this->fail('Only platform-owned Blocks can be placed.');
            } catch (LogicException) {
                $this->assertSame(0, BlockInstance::query()->where('block_version_id', $version->id)->count());
            }
        }
    }

    public function test_used_page_and_block_version_cannot_be_hard_deleted(): void
    {
        $instance = BlockInstance::factory()->create();

        try {
            $instance->page->delete();
            $this->fail('A Page with Block Instances must not be deleted implicitly.');
        } catch (QueryException) {
            $this->assertNotNull($instance->page->fresh());
        }

        $this->expectException(QueryException::class);

        $instance->version->delete();
    }
}
