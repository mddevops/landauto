<?php

namespace Tests\Feature\Blocks;

use App\Exceptions\InvalidBlockSchemaException;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class BlockFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_tables_separate_definition_from_versioned_schema(): void
    {
        $this->assertTrue(Schema::hasColumns('block_definitions', ['id', 'public_id', 'name', 'slug', 'owner_scope', 'developer_profile_id', 'workspace_id', 'created_by_user_id', 'updated_by_user_id']));
        $this->assertTrue(Schema::hasColumns('block_versions', ['id', 'block_definition_id', 'version', 'schema_json', 'created_at']));
        $this->assertFalse(Schema::hasColumn('block_versions', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('block_definitions', 'is_official'));
    }

    public function test_definition_has_versions_and_serializes_safely(): void
    {
        $definition = BlockDefinition::factory()->create(['name' => 'Hero', 'slug' => 'hero']);
        $schema = ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Заголовок']]];
        $version = BlockVersion::factory()->for($definition, 'definition')->create(['schema_json' => $schema]);

        $this->assertTrue(Str::isUlid($definition->public_id));
        $this->assertSame('public_id', $definition->getRouteKeyName());
        $this->assertTrue($definition->isPlatformOwned());
        $this->assertTrue($definition->versions()->sole()->is($version));
        $this->assertTrue($version->definition->is($definition));
        $this->assertSame($schema, $version->fresh()?->schema_json);
        $this->assertArrayNotHasKey('id', $definition->toArray());
        $this->assertArrayNotHasKey('id', $version->toArray());
        $this->assertArrayNotHasKey('block_definition_id', $version->toArray());
    }

    public function test_version_is_unique_per_definition(): void
    {
        $definition = BlockDefinition::factory()->create();
        BlockVersion::factory()->for($definition, 'definition')->create(['version' => '1.0.0']);
        BlockVersion::factory()->for($definition, 'definition')->create(['version' => '1.1.0']);
        BlockVersion::factory()->create(['version' => '1.0.0']);

        $this->expectException(QueryException::class);

        BlockVersion::factory()->for($definition, 'definition')->create(['version' => '1.0.0']);
    }

    public function test_definition_slug_is_unique(): void
    {
        BlockDefinition::factory()->create(['slug' => 'footer']);

        $this->expectException(QueryException::class);

        BlockDefinition::factory()->create(['slug' => 'footer']);
    }

    public function test_block_version_is_immutable(): void
    {
        $version = BlockVersion::factory()->create(['schema_json' => ['fields' => []]]);

        try {
            $version->update(['schema_json' => ['fields' => [['key' => 'injected']]]]);
            $this->fail('Block versions must be immutable.');
        } catch (LogicException) {
            $this->assertSame(['fields' => []], $version->fresh()?->schema_json);
        }
    }

    public function test_invalid_schema_cannot_be_stored_as_block_version(): void
    {
        $definition = BlockDefinition::factory()->create();

        try {
            BlockVersion::factory()->for($definition, 'definition')->create([
                'schema_json' => ['fields' => [['key' => 'title', 'type' => 'html', 'label' => 'HTML']]],
            ]);
            $this->fail('Invalid Block Schema must be rejected.');
        } catch (InvalidBlockSchemaException $exception) {
            $this->assertArrayHasKey('fields.0.type', $exception->errors);
        }

        $this->assertSame(0, $definition->versions()->count());
    }

    public function test_definition_with_versions_cannot_be_hard_deleted(): void
    {
        $version = BlockVersion::factory()->create();

        $this->expectException(QueryException::class);

        $version->definition->delete();
    }
}
