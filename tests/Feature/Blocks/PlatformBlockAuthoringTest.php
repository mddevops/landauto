<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockAuthoring;
use App\Blocks\BlockAuthoringAuthorization;
use App\Blocks\OfficialBlockCatalog;
use App\Enums\BlockOwnerScope;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery;
use Tests\TestCase;

class PlatformBlockAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);
    }

    public function test_super_admin_creates_platform_blocks_without_a_developer_profile(): void
    {
        Log::spy();
        BlockDefinition::factory()->developer()->create(['slug' => 'developer-only']);

        $this->actingAs($this->superAdmin)->get(route('platform.blocks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/blocks/index')
                ->has('blocks', 12)
                ->where('blocks', fn ($blocks): bool => collect($blocks)->pluck('slug')->doesntContain('developer-only'))
                ->missing('blocks.0.id'));
        $this->actingAs($this->superAdmin)->get(route('platform.blocks.create'))->assertOk();

        $response = $this->actingAs($this->superAdmin)->post(route('platform.blocks.store'), [
            'name' => 'Кредитный калькулятор',
            'slug' => 'credit-calculator',
            'owner_scope' => 'developer',
            'developer_profile_id' => DeveloperProfile::factory()->create()->id,
        ]);

        $block = BlockDefinition::query()->where('slug', 'credit-calculator')->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('platform.blocks.show', $block));

        $this->assertNull($this->superAdmin->developerProfile);
        $this->assertSame(BlockOwnerScope::Platform, $block->owner_scope);
        $this->assertNull($block->developer_profile_id);
        $this->assertNull($block->workspace_id);
        $this->assertSame($this->superAdmin->id, $block->created_by_user_id);
        $this->assertSame(0, $block->versions()->count());
        Log::shouldHaveReceived('info')->with('platform.block_created', Mockery::on(fn (array $context): bool => $context === [
            'block' => $block->public_id,
            'owner_scope' => 'platform',
            'actor_user_id' => $this->superAdmin->id,
        ]));

        $this->actingAs($this->superAdmin)->get(route('platform.blocks.show', $block))
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/blocks/show')
                ->where('block.owner_name', 'Landflow')
                ->where('block.owner_scope_label', 'Платформа Landflow')
                ->where('block.versions_count', 0));

        $this->actingAs($this->superAdmin)->patch(route('platform.blocks.update', $block), ['name' => 'Калькулятор кредита', 'slug' => 'other'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Калькулятор кредита', 'credit-calculator'], [$block->fresh()?->name, $block->fresh()?->slug]);
        Log::shouldHaveReceived('info')->with('platform.block_updated', Mockery::any())->once();

        $this->actingAs($this->superAdmin)->post(route('platform.blocks.store'), ['name' => 'Дубль', 'slug' => 'developer-only'])
            ->assertSessionHasErrors(['slug' => 'Этот slug уже используется другим блоком.']);
    }

    public function test_existing_official_blocks_show_their_versions(): void
    {
        $hero = BlockDefinition::query()->where('slug', 'hero')->sole();

        $this->actingAs($this->superAdmin)->get(route('platform.blocks.show', $hero))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.slug', 'hero')
                ->where('block.versions_count', $hero->versions()->count()));
    }

    public function test_platform_routes_never_reach_developer_or_private_blocks(): void
    {
        foreach ([BlockDefinition::factory()->developer()->create(), BlockDefinition::factory()->workspacePrivate()->create()] as $block) {
            $this->actingAs($this->superAdmin)->get(route('platform.blocks.show', $block))->assertNotFound();
            $this->actingAs($this->superAdmin)->patch(route('platform.blocks.update', $block), ['name' => 'Взлом'])->assertNotFound();
            $this->assertNotSame('Взлом', $block->fresh()?->name);
        }
    }

    public function test_only_manage_platform_content_reaches_platform_blocks(): void
    {
        $hero = BlockDefinition::query()->where('slug', 'hero')->sole();
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $users = [
            'catalog manager' => $this->userWithRole(PlatformRole::CatalogManager),
            'developer' => DeveloperProfile::factory()->withPermissions()->create()->user,
            'workspace owner' => $owner,
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('platform.blocks.index'))->assertForbidden();
            $this->actingAs($user)->get(route('platform.blocks.create'))->assertForbidden();
            $this->actingAs($user)->post(route('platform.blocks.store'), ['name' => 'Блок', 'slug' => 'forbidden-block'])->assertForbidden();
            $this->actingAs($user)->get(route('platform.blocks.show', $hero))->assertForbidden();
            $this->actingAs($user)->patch(route('platform.blocks.update', $hero), ['name' => 'Взлом'])->assertForbidden();
        }

        $this->assertDatabaseMissing('block_definitions', ['slug' => 'forbidden-block']);
        $this->assertSame('Первый экран', $hero->fresh()?->name);

        auth()->logout();
        $this->get(route('platform.blocks.index'))->assertRedirect(route('login'));
    }

    public function test_authoring_domains_never_grant_each_other(): void
    {
        $authoring = app(BlockAuthoring::class);
        $authorization = app(BlockAuthoringAuthorization::class);
        $developer = DeveloperProfile::factory()->withPermissions()->create();
        $developerBlock = BlockDefinition::factory()->developer($developer)->create();
        $platformBlock = BlockDefinition::query()->where('slug', 'hero')->sole();
        $private = BlockDefinition::factory()->workspacePrivate()->create();
        $workspaceOwner = User::factory()->create();
        $private->workspace?->addMember($workspaceOwner, WorkspaceRole::Owner);

        $attempts = [
            'developer creates platform block' => fn () => $authoring->createPlatform($developer->user, 'Блок', 'dev-platform'),
            'super admin without profile creates developer block' => fn () => $authoring->createDeveloper($this->superAdmin, 'Блок', 'admin-developer'),
            'developer edits platform block' => fn () => $authoring->updateMetadata($developer->user, $platformBlock, 'Взлом'),
            'super admin edits developer block' => fn () => $authoring->updateMetadata($this->superAdmin, $developerBlock, 'Взлом'),
            'workspace owner edits private block' => fn () => $authoring->updateMetadata($workspaceOwner, $private, 'Взлом'),
            'super admin edits private block' => fn () => $authoring->updateMetadata($this->superAdmin, $private, 'Взлом'),
        ];

        foreach ($attempts as $case => $attempt) {
            try {
                $attempt();
                $this->fail("Must be denied: {$case}.");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseMissing('block_definitions', ['slug' => 'dev-platform']);
        $this->assertDatabaseMissing('block_definitions', ['slug' => 'admin-developer']);
        $this->assertFalse($authorization->canAuthorPlatformBlocks($developer->user));
        $this->assertNull($authorization->developerAuthor($this->superAdmin));
        $this->assertTrue($authorization->canEdit($developer->user, $developerBlock));
        $this->assertFalse($authorization->canEdit($developer->user, BlockDefinition::factory()->developer()->create()));

        // A suspended profile authors nothing, even with stored permissions.
        $developer->forceFill(['status' => 'suspended'])->save();
        $this->assertFalse($authorization->canEdit($developer->user, $developerBlock));
        $this->assertSame(0, $developer->user->memberships()->count());
    }

    public function test_new_blocks_stay_out_of_the_customer_designer_and_runtime(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Designer);
        $site = Site::factory()->for($workspace)->create();
        $page = Page::factory()->for($site)->home()->create();
        $session = [WorkspaceContext::SESSION_KEY => $workspace->public_id];

        $developerDraft = BlockDefinition::factory()->developer()->create(['slug' => 'developer-draft']);
        $developerVersioned = BlockDefinition::factory()->developer()->create(['slug' => 'developer-versioned']);
        $developerVersion = BlockVersion::factory()->for($developerVersioned, 'definition')->create();
        app(BlockAuthoring::class)->createPlatform($this->superAdmin, 'Новый официальный', 'new-official');
        $official = array_values(array_unique(array_column(OfficialBlockCatalog::blocks(), 'slug')));

        $this->actingAs($user)->withSession($session)->get(route('sites.designer', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia->where(
                'library',
                fn ($library): bool => collect($library)->pluck('slug')->sort()->values()->all() === collect($official)->sort()->values()->all(),
            ));

        foreach (['developer-draft', 'developer-versioned', 'new-official'] as $slug) {
            $this->actingAs($user)->withSession($session)
                ->post(route('sites.blocks.store', [$site, $page]), ['block' => $slug])
                ->assertSessionHasErrors('block');
        }
        $this->assertSame(0, BlockInstance::query()->count());

        $this->expectException(LogicException::class);
        BlockInstance::factory()->for($page)->for($developerVersion, 'version')->create();
        $this->assertSame(0, $developerDraft->versions()->count());
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
