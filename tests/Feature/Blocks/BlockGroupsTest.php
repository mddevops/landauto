<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockGroups;
use App\Enums\BlockCategory;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockGroup;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlockGroupsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $this->superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
    }

    public function test_official_blocks_start_their_own_groups_and_other_blocks_join_by_category(): void
    {
        $developerFooter = BlockDefinition::factory()->developer()->create(['category' => BlockCategory::Footer]);
        $developerGallery = BlockDefinition::factory()->developer()->create(['category' => BlockCategory::Gallery]);

        $this->seed(OfficialBlockSeeder::class);

        $footer = BlockDefinition::query()->where('slug', 'footer')->sole();
        $header = BlockDefinition::query()->where('slug', 'header')->sole();
        $this->assertSame($footer->name, $footer->group?->name);
        $this->assertSame(BlockCategory::Footer, $footer->group->kind);
        $this->assertSame($footer->block_group_id, $developerFooter->fresh()?->block_group_id, 'a developer footer joins the official footer group');
        $this->assertSame('Галерея', $developerGallery->fresh()?->group?->name);
        $this->assertLessThan($footer->group->sort_order, $header->group?->sort_order, 'groups follow the official catalog order');
        $this->assertSame(0, BlockDefinition::query()->whereNull('block_group_id')->count());

        $groups = BlockGroup::query()->count();
        $this->seed(OfficialBlockSeeder::class);
        $this->assertSame($groups, BlockGroup::query()->count(), 'the backfill is idempotent');
    }

    public function test_super_admin_creates_renames_orders_and_removes_empty_groups(): void
    {
        $this->actingAs($this->superAdmin)->post(route('platform.blocks.groups.store'), ['name' => ' Баннеры ', 'kind' => 'cta'])
            ->assertSessionHasNoErrors();
        $banners = BlockGroup::query()->where('name', 'Баннеры')->sole();
        $this->assertSame(BlockCategory::Cta, $banners->kind);

        $this->actingAs($this->superAdmin)->post(route('platform.blocks.groups.store'), ['name' => 'Баннеры', 'kind' => 'cta'])
            ->assertSessionHasErrors(['name' => 'Группа с таким названием уже есть.']);

        $this->actingAs($this->superAdmin)->post(route('platform.blocks.groups.store'), ['name' => 'Промо', 'kind' => 'cta']);
        $promo = BlockGroup::query()->where('name', 'Промо')->sole();
        $this->assertGreaterThan($banners->sort_order, $promo->sort_order);

        $block = BlockDefinition::factory()->create(['category' => BlockCategory::Cta]);
        $block->group()->associate($banners)->save();

        $this->actingAs($this->superAdmin)->get(route('platform.blocks.groups.show', $banners))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/blocks/group')
                ->where('group.name', 'Баннеры')
                ->has('blocks', 1)
                ->where('blocks.0.group_label', 'Баннеры')
                ->where('canDelete', false)
                ->missing('group.id'));

        $this->actingAs($this->superAdmin)->patch(route('platform.blocks.groups.update', $banners), ['name' => 'Баннеры', 'kind' => 'content'])
            ->assertSessionHasNoErrors();
        $this->assertSame(BlockCategory::Content, $block->fresh()?->category, 'the group type is the category of its Blocks');

        $this->actingAs($this->superAdmin)->post(route('platform.blocks.groups.move', $promo), ['direction' => 'up'])->assertRedirect(route('platform.blocks.index'));
        $this->assertLessThan($banners->fresh()?->sort_order, $promo->fresh()?->sort_order);

        $this->actingAs($this->superAdmin)->delete(route('platform.blocks.groups.destroy', $banners));
        $this->assertModelExists($banners);
        $this->actingAs($this->superAdmin)->delete(route('platform.blocks.groups.destroy', $promo))->assertRedirect(route('platform.blocks.index'));
        $this->assertModelMissing($promo);
    }

    public function test_only_platform_content_managers_manage_groups(): void
    {
        $group = BlockGroup::factory()->create();
        $developer = DeveloperProfile::factory()->withPermissions()->create()->user;

        $this->actingAs($developer)->post(route('platform.blocks.groups.store'), ['name' => 'Взлом', 'kind' => 'other'])->assertForbidden();
        $this->actingAs($developer)->patch(route('platform.blocks.groups.update', $group), ['name' => 'Взлом', 'kind' => 'other'])->assertForbidden();
        $this->actingAs($developer)->delete(route('platform.blocks.groups.destroy', $group))->assertForbidden();
        $this->assertModelExists($group);
        $this->assertDatabaseMissing('block_groups', ['name' => 'Взлом']);
    }

    public function test_library_order_follows_groups_then_official_blocks(): void
    {
        $first = BlockGroup::factory()->create(['sort_order' => 1]);
        $second = BlockGroup::factory()->create(['sort_order' => 2]);
        $developer = BlockDefinition::factory()->developer()->create();
        $developer->group()->associate($first)->save();
        $official = BlockDefinition::factory()->create();
        $official->group()->associate($first)->save();
        $later = BlockDefinition::factory()->create();
        $later->group()->associate($second)->save();
        $loose = BlockDefinition::factory()->create();

        $order = collect([$loose, $later, $developer, $official])
            ->each(fn (BlockDefinition $block) => $block->load('group'))
            ->sortBy(fn (BlockDefinition $block): string => BlockGroups::libraryOrder($block))
            ->map(fn (BlockDefinition $block): int => $block->id)
            ->values()
            ->all();

        $this->assertSame([$official->id, $developer->id, $later->id, $loose->id], $order);
    }
}
