<?php

namespace Tests\Feature\Blocks;

use App\Enums\BlockOwnerScope;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class DeveloperBlockAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = DeveloperProfile::factory()->withPermissions()->create(['display_name' => 'Студия А']);
        $this->developer = $this->profile->user;
    }

    public function test_developer_creates_a_block_owned_by_their_own_profile(): void
    {
        Log::spy();
        $other = DeveloperProfile::factory()->withPermissions()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($this->developer)->get(route('developer.blocks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('developer/blocks/index')->has('blocks', 0));
        $this->actingAs($this->developer)->get(route('developer.blocks.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('developer/blocks/create'));

        $response = $this->actingAs($this->developer)->post(route('developer.blocks.store'), [
            'name' => ' Карточка акции ',
            'slug' => 'promo-card',
            'owner_scope' => 'platform',
            'developer_profile_id' => $other->id,
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $other->user_id,
        ]);

        $block = BlockDefinition::query()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $block))
            ->assertInertiaFlash('toast.message', 'Блок «Карточка акции» создан.');

        $this->assertSame(BlockOwnerScope::Developer, $block->owner_scope);
        $this->assertSame($this->profile->id, $block->developer_profile_id);
        $this->assertNull($block->workspace_id);
        $this->assertSame($this->developer->id, $block->created_by_user_id);
        $this->assertSame($this->developer->id, $block->updated_by_user_id);
        $this->assertSame(['Карточка акции', 'promo-card'], [$block->name, $block->slug]);
        $this->assertTrue(Str::isUlid($block->public_id));
        $this->assertSame(0, BlockVersion::query()->count(), 'no Block Version is created');

        Log::shouldHaveReceived('info')->with('developer.block_created', Mockery::on(fn (array $context): bool => $context === [
            'block' => $block->public_id,
            'owner_scope' => 'developer',
            'developer_profile' => $this->profile->public_id,
            'actor_user_id' => $this->developer->id,
        ]));

        $this->actingAs($this->developer)->get(route('developer.blocks.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 1)
                ->where('blocks.0.public_id', $block->public_id)
                ->where('blocks.0.slug', 'promo-card')
                ->where('blocks.0.versions_count', 0)
                ->missing('blocks.0.id')
                ->missing('blocks.0.developer_profile_id'));
    }

    public function test_developer_edits_the_name_but_never_the_slug_or_ownership(): void
    {
        Log::spy();
        $block = BlockDefinition::factory()->developer($this->profile)->create(['name' => 'Старое', 'slug' => 'my-block', 'created_by_user_id' => $this->developer->id]);
        $other = DeveloperProfile::factory()->create();

        $this->actingAs($this->developer)->get(route('developer.blocks.show', $block))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/blocks/show')
                ->where('block.public_id', $block->public_id)
                ->where('block.slug', 'my-block')
                ->where('block.owner_scope', 'developer')
                ->where('block.owner_scope_label', 'Разработчик')
                ->where('block.owner_name', 'Студия А')
                ->where('block.versions_count', 0)
                ->missing('block.id')
                ->missing('block.developer_profile_id')
                ->missing('block.workspace_id')
                ->missing('block.created_by_user_id')
                ->missing('block.updated_by_user_id'));

        $this->actingAs($this->developer)
            ->patch(route('developer.blocks.update', $block), [
                'name' => 'Новое название',
                'slug' => 'hijacked',
                'owner_scope' => 'platform',
                'developer_profile_id' => $other->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $block));

        $block->refresh();
        $this->assertSame(['Новое название', 'my-block', BlockOwnerScope::Developer, $this->profile->id], [$block->name, $block->slug, $block->owner_scope, $block->developer_profile_id]);
        $this->assertSame($this->developer->id, $block->updated_by_user_id);
        Log::shouldHaveReceived('info')->with('developer.block_updated', Mockery::any())->once();

        $this->actingAs($this->developer)->patch(route('developer.blocks.update', $block), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->developer)->patch(route('developer.blocks.update', $block), ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
    }

    public function test_slug_must_be_valid_and_globally_unique(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        BlockDefinition::factory()->developer()->create(['slug' => 'taken-by-b']);

        foreach (['Promo', 'promo card', '-promo', 'promo-', 'pro--mo', 'промо', 'ab', str_repeat('a', 61)] as $slug) {
            $this->actingAs($this->developer)
                ->post(route('developer.blocks.store'), ['name' => 'Блок', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }

        foreach (['hero', 'vehicle-card', 'taken-by-b'] as $slug) {
            $this->actingAs($this->developer)
                ->post(route('developer.blocks.store'), ['name' => 'Блок', 'slug' => $slug])
                ->assertSessionHasErrors(['slug' => 'Этот slug уже используется другим блоком.']);
        }

        $this->actingAs($this->developer)->post(route('developer.blocks.store'), ['slug' => 'valid-slug'])->assertSessionHasErrors('name');
        $this->assertSame(0, BlockDefinition::query()->ownedByDeveloper($this->profile)->count());
    }

    public function test_developer_cannot_reach_another_developers_or_platform_blocks(): void
    {
        $foreign = BlockDefinition::factory()->developer(DeveloperProfile::factory()->withPermissions()->create())->create(['name' => 'Чужой', 'slug' => 'foreign']);
        $platform = BlockDefinition::factory()->platform()->create(['name' => 'Официальный']);
        $private = BlockDefinition::factory()->workspacePrivate()->create(['name' => 'Приватный']);
        BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'mine']);

        $this->actingAs($this->developer)->get(route('developer.blocks.index'))
            ->assertInertia(fn (Assert $page) => $page->has('blocks', 1)->where('blocks.0.slug', 'mine'));

        foreach ([$foreign, $platform, $private] as $block) {
            $this->actingAs($this->developer)->get(route('developer.blocks.show', $block))->assertNotFound();
            $this->actingAs($this->developer)->patch(route('developer.blocks.update', $block), ['name' => 'Взлом'])->assertNotFound();
            $this->assertNotSame('Взлом', $block->fresh()?->name);
        }

        $this->actingAs($this->developer)->get(route('developer.blocks.show', (string) Str::ulid()))->assertNotFound();
        $this->actingAs($this->developer)->get('/developer/blocks/'.$foreign->id)->assertNotFound();
    }

    public function test_revoking_create_blocks_closes_authoring_but_keeps_blocks(): void
    {
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);
        $block = BlockDefinition::factory()->developer($this->profile)->create(['name' => 'Мой блок']);

        $this->actingAs($superAdmin)->put(route('platform.developers.permissions.update', $this->profile), ['permissions' => ['create_templates']]);

        $this->assertAuthoringStatus(403, $block);
        $this->actingAs($this->developer)->get(route('developer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('capabilities.create_blocks', false));
        $this->assertSame('Мой блок', $block->fresh()?->name);
        $this->assertSame($this->profile->id, $block->fresh()?->developer_profile_id);

        $this->actingAs($superAdmin)->put(route('platform.developers.permissions.update', $this->profile), ['permissions' => ['create_blocks']]);
        $this->actingAs($this->developer)->get(route('developer.blocks.show', $block))->assertOk();
    }

    public function test_suspended_developers_and_other_users_cannot_author_developer_blocks(): void
    {
        $block = BlockDefinition::factory()->developer($this->profile)->create(['name' => 'Мой блок']);
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);

        $this->actingAs($superAdmin)->post(route('platform.developers.suspend', $this->profile));
        $this->assertAuthoringStatus(403, $block);
        $this->assertSame('Мой блок', $block->fresh()?->name);

        $this->actingAs($superAdmin)->post(route('platform.developers.reactivate', $this->profile));
        $this->actingAs($this->developer)->get(route('developer.blocks.show', $block))->assertOk();

        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $catalogManager = $this->userWithRole(PlatformRole::CatalogManager);

        // Workspace roles and platform staff authority never grant Developer authoring.
        foreach ([$owner, $catalogManager, $superAdmin, User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('developer.blocks.index'))->assertForbidden();
            $this->actingAs($user)->post(route('developer.blocks.store'), ['name' => 'Блок', 'slug' => 'user-'.$user->id])->assertForbidden();
            $this->actingAs($user)->get(route('developer.blocks.show', $block))->assertForbidden();
        }

        $this->assertSame(1, BlockDefinition::query()->count());

        auth()->logout();
        $this->get(route('developer.blocks.index'))->assertRedirect(route('login'));
    }

    private function assertAuthoringStatus(int $status, BlockDefinition $block): void
    {
        $requests = [
            fn (): TestResponse => $this->actingAs($this->developer)->get(route('developer.blocks.index')),
            fn (): TestResponse => $this->actingAs($this->developer)->get(route('developer.blocks.create')),
            fn (): TestResponse => $this->actingAs($this->developer)->post(route('developer.blocks.store'), ['name' => 'Блок', 'slug' => 'denied-block']),
            fn (): TestResponse => $this->actingAs($this->developer)->get(route('developer.blocks.show', $block)),
            fn (): TestResponse => $this->actingAs($this->developer)->patch(route('developer.blocks.update', $block), ['name' => 'Взлом']),
        ];

        foreach ($requests as $request) {
            $request()->assertStatus($status);
        }

        $this->assertDatabaseMissing('block_definitions', ['slug' => 'denied-block']);
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
