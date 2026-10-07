<?php

namespace Tests\Feature\Blocks;

use App\Enums\BlockOwnerScope;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class BlockOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_states_express_explicit_ownership(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $workspace = Workspace::factory()->create();

        $default = BlockDefinition::factory()->create();
        $platform = BlockDefinition::factory()->platform()->create();
        $developer = BlockDefinition::factory()->developer($profile)->create();
        $private = BlockDefinition::factory()->workspacePrivate($workspace)->create();

        $this->assertSame(BlockOwnerScope::Platform, $default->owner_scope);
        $this->assertTrue($platform->isPlatformOwned());
        $this->assertNull($platform->developer_profile_id);
        $this->assertNull($platform->workspace_id);
        $this->assertTrue($developer->isDeveloperOwned());
        $this->assertTrue($developer->developerProfile?->is($profile));
        $this->assertNull($developer->workspace_id);
        $this->assertTrue($private->isWorkspacePrivate());
        $this->assertTrue($private->workspace?->is($workspace));
        $this->assertNull($private->developer_profile_id);

        foreach (['id', 'developer_profile_id', 'workspace_id', 'created_by_user_id', 'updated_by_user_id'] as $key) {
            $this->assertArrayNotHasKey($key, $developer->toArray(), $key);
        }
    }

    public function test_invalid_ownership_combinations_are_rejected(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $workspace = Workspace::factory()->create();

        $invalid = [
            'platform + developer' => [BlockOwnerScope::Platform, $profile->id, null],
            'platform + workspace' => [BlockOwnerScope::Platform, null, $workspace->id],
            'developer without profile' => [BlockOwnerScope::Developer, null, null],
            'developer + workspace' => [BlockOwnerScope::Developer, $profile->id, $workspace->id],
            'private without workspace' => [BlockOwnerScope::WorkspacePrivate, null, null],
            'private + developer' => [BlockOwnerScope::WorkspacePrivate, $profile->id, $workspace->id],
            'missing scope' => [null, null, null],
        ];

        foreach ($invalid as $case => [$scope, $developerId, $workspaceId]) {
            $block = new BlockDefinition(['name' => 'Блок', 'slug' => 'block-'.md5($case)]);
            $block->forceFill(['owner_scope' => $scope, 'developer_profile_id' => $developerId, 'workspace_id' => $workspaceId]);

            try {
                $block->save();
                $this->fail("Invalid ownership must be rejected: {$case}.");
            } catch (LogicException) {
                $this->assertFalse($block->exists, $case);
            }
        }

        $this->assertSame(0, BlockDefinition::query()->count());
    }

    public function test_ownership_slug_and_creator_are_immutable(): void
    {
        $creator = User::factory()->create();
        $block = BlockDefinition::factory()->developer()->create(['slug' => 'stable-slug', 'created_by_user_id' => $creator->id]);
        $changes = [
            'slug' => 'new-slug',
            'owner_scope' => BlockOwnerScope::Platform,
            'developer_profile_id' => DeveloperProfile::factory()->create()->id,
            'workspace_id' => Workspace::factory()->create()->id,
            'created_by_user_id' => User::factory()->create()->id,
        ];

        foreach ($changes as $attribute => $value) {
            $fresh = $block->fresh() ?? $block;

            try {
                $fresh->forceFill([$attribute => $value])->save();
                $this->fail("{$attribute} must be immutable.");
            } catch (LogicException) {
                $this->assertSame($block->getRawOriginal($attribute), $block->fresh()?->getRawOriginal($attribute), $attribute);
            }
        }

        $fresh = $block->fresh() ?? $block;
        $fresh->forceFill(['name' => 'Новое название'])->save();
        $this->assertSame('Новое название', $block->fresh()?->name);
    }

    public function test_actor_deletion_keeps_block_ownership_and_owners_are_restricted(): void
    {
        $actor = User::factory()->create();
        $platform = BlockDefinition::factory()->platform()->create(['created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $developer = BlockDefinition::factory()->developer()->create();

        $actor->delete();
        $platform->refresh();
        $this->assertTrue($platform->isPlatformOwned());
        $this->assertNull($platform->created_by_user_id);
        $this->assertNull($platform->updated_by_user_id);

        $this->expectException(QueryException::class);
        DB::table('developer_profiles')->where('id', $developer->developer_profile_id)->delete();
    }

    public function test_migration_backfills_existing_definitions_as_platform_and_drops_is_official(): void
    {
        // SQLite rebuilds the table inside the test transaction, so the rows carry no Block Versions here.
        BlockDefinition::factory()->count(3)->platform()->create();
        $migration = require database_path('migrations/2026_10_12_000003_add_ownership_to_block_definitions_table.php');

        $migration->down();
        $this->assertTrue(Schema::hasColumn('block_definitions', 'is_official'));
        $this->assertFalse(Schema::hasColumn('block_definitions', 'owner_scope'));
        $this->assertSame(3, DB::table('block_definitions')->where('is_official', true)->count());

        $migration->up();

        $this->assertFalse(Schema::hasColumn('block_definitions', 'is_official'));
        $this->assertSame(3, BlockDefinition::query()->platformOwned()->count());
        $this->assertSame(0, DB::table('block_definitions')->whereNotNull('developer_profile_id')->orWhereNotNull('workspace_id')->count());
    }

    public function test_official_seeder_is_idempotent_and_never_takes_over_a_developer_slug(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $schemas = BlockVersion::query()->orderBy('id')->pluck('schema_json', 'id')->all();

        $this->seed(OfficialBlockSeeder::class);

        $this->assertSame(count(OfficialBlocksTest::SLUGS), BlockDefinition::query()->platformOwned()->count());
        $this->assertSame($schemas, BlockVersion::query()->orderBy('id')->pluck('schema_json', 'id')->all());

        BlockDefinition::query()->where('slug', 'hero')->firstOrFail()->versions()->delete();
        DB::table('block_definitions')->where('slug', 'hero')->delete();
        $developerBlock = BlockDefinition::factory()->developer()->create(['slug' => 'hero']);

        try {
            $this->seed(OfficialBlockSeeder::class);
            $this->fail('The seeder must not change the owner of an existing Block.');
        } catch (LogicException) {
            $this->assertTrue($developerBlock->fresh()?->isDeveloperOwned());
            $this->assertSame(0, $developerBlock->versions()->count());
        }
    }
}
