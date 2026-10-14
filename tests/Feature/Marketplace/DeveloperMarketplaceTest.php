<?php

namespace Tests\Feature\Marketplace;

use App\Enums\CatalogAccessMode;
use App\Enums\DeveloperPermission;
use App\Enums\MarketplaceListingStatus;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\PlatformRoleAssignment;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeveloperMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private DeveloperProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the Marketplace permission: creator permissions are not required (D-118).
        $this->profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::SubmitMarketplaceItem)->create(['display_name' => 'Студия Альфа']);
        $this->developer = $this->profile->user;
    }

    public function test_developer_lists_own_published_block_and_template_as_drafts(): void
    {
        Log::spy();
        $block = $this->publishedBlock($this->profile, ['name' => 'Витрина акций']);
        $template = $this->publishedTemplate($this->profile, ['name' => 'Лендинг дилера']);

        $response = $this->store([
            'product_type' => 'block',
            'product' => $block->public_id,
            'title' => '  Витрина акций для дилеров  ',
            'slug' => 'promo-showcase',
            'description' => "Первая строка\nВторая строка",
        ]);
        $listing = MarketplaceListing::query()->where('slug', 'promo-showcase')->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('developer.marketplace.show', $listing->public_id));

        $this->assertSame('Витрина акций для дилеров', $listing->title);
        $this->assertSame("Первая строка\nВторая строка", $listing->description);
        $this->assertSame(MarketplaceListingStatus::Draft, $listing->status);
        $this->assertNull($listing->published_at);
        $this->assertSame($block->id, $listing->block_definition_id);
        $this->assertSame($this->profile->id, $listing->developer_profile_id);
        $this->assertSame($this->developer->id, $listing->created_by_user_id);
        Log::shouldHaveReceived('info')->with('developer.marketplace_listing_created', Mockery::on(fn (array $context): bool => $context === [
            'listing' => $listing->public_id,
            'product_type' => 'block',
            'block' => $block->public_id,
            'status' => 'draft',
            'developer_profile' => $this->profile->public_id,
            'actor_user_id' => $this->developer->id,
        ]));

        $this->store(['product_type' => 'template', 'product' => $template->public_id, 'title' => 'Лендинг дилера', 'slug' => 'dealer-landing'])
            ->assertSessionHasNoErrors();
        $this->assertSame($template->id, MarketplaceListing::query()->where('slug', 'dealer-landing')->sole()->template_id);

        $this->actingAs($this->developer)->get(route('developer.marketplace.show', $listing->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/marketplace/show')
                ->where('listing.public_id', $listing->public_id)
                ->where('listing.status', 'draft')
                ->where('listing.status_label', 'Черновик')
                ->where('listing.product_type_label', 'Блок')
                ->where('listing.product_name', 'Витрина акций')
                ->where('listing.author', 'Студия Альфа')
                ->where('listing.access.label', 'Бесплатно')
                ->where('listing.has_published_version', true)
                ->where('listing.publicly_visible', false)
                ->where('listing.publication_denial', null)
                ->missing('listing.id')
                ->missing('listing.block_definition_id')
                ->missing('listing.developer_profile_id')
                ->missing('listing.created_by_user_id'));

        $this->actingAs($this->developer)->get(route('developer.marketplace.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/marketplace/index')
                ->has('listings', 2)
                ->has('products', 0)
                ->missing('listings.0.id')
                ->where('productTypes', [['value' => 'block', 'label' => 'Блок'], ['value' => 'template', 'label' => 'Шаблон']]));
    }

    public function test_owner_is_derived_from_the_product_never_from_the_browser(): void
    {
        $block = $this->publishedBlock($this->profile);
        $other = DeveloperProfile::factory()->create();

        $this->store([
            'product_type' => 'block',
            'product' => $block->public_id,
            'title' => 'Карточка',
            'slug' => 'owner-derived',
            'developer_profile_id' => $other->id,
            'owner_scope' => 'platform',
            'status' => 'published',
            'site_price_minor' => 100,
        ])->assertSessionHasNoErrors();

        $listing = MarketplaceListing::query()->sole();
        $this->assertSame($this->profile->id, $listing->developer_profile_id);
        $this->assertSame(MarketplaceListingStatus::Draft, $listing->status);
    }

    public function test_developer_cannot_list_foreign_platform_or_workspace_private_products(): void
    {
        $foreignBlock = $this->publishedBlock(DeveloperProfile::factory()->create());
        $foreignTemplate = $this->publishedTemplate(DeveloperProfile::factory()->create());
        $platformBlock = BlockDefinition::factory()->platform()->create();
        BlockVersion::factory()->for($platformBlock, 'definition')->create();
        $platformTemplate = Template::factory()->published()->create();
        $privateBlock = BlockDefinition::factory()->workspacePrivate()->create();

        foreach ([
            ['block', $foreignBlock->public_id, 'Выберите свой блок из списка.'],
            ['block', $platformBlock->public_id, 'Выберите свой блок из списка.'],
            ['block', $privateBlock->public_id, 'Выберите свой блок из списка.'],
            ['block', $this->publishedTemplate($this->profile)->public_id, 'Выберите свой блок из списка.'],
            ['template', $foreignTemplate->public_id, 'Выберите свой шаблон из списка.'],
            ['template', $platformTemplate->public_id, 'Выберите свой шаблон из списка.'],
            ['template', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'Выберите свой шаблон из списка.'],
        ] as $index => [$type, $product, $message]) {
            $this->store(['product_type' => $type, 'product' => $product, 'title' => 'Чужая карточка', 'slug' => "foreign-{$index}"])
                ->assertSessionHasErrors(['product' => $message]);
        }

        $this->assertSame(0, MarketplaceListing::query()->count());
    }

    public function test_index_lists_only_own_listings_and_own_unlisted_products(): void
    {
        $listed = $this->publishedBlock($this->profile, ['name' => 'Уже в Marketplace']);
        MarketplaceListing::factory()->forProduct($listed)->create(['title' => 'Своя карточка']);
        $this->publishedBlock($this->profile, ['name' => 'Без карточки']);
        BlockDefinition::factory()->developer($this->profile)->create(['name' => 'Черновой блок']);
        MarketplaceListing::factory()->forProduct($this->publishedBlock(DeveloperProfile::factory()->create()))->create(['title' => 'Чужая карточка']);
        MarketplaceListing::factory()->forProduct(BlockDefinition::factory()->platform()->create())->create(['title' => 'Карточка Landflow']);
        BlockDefinition::factory()->platform()->create(['name' => 'Блок Landflow']);
        BlockDefinition::factory()->workspacePrivate()->create(['name' => 'Частный блок']);

        $this->actingAs($this->developer)->get(route('developer.marketplace.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('listings', 1)
                ->where('listings.0.title', 'Своя карточка')
                ->where('products', fn ($products): bool => collect($products)->pluck('name')->sort()->values()->all() === ['Без карточки', 'Черновой блок'])
                ->where('products', fn ($products): bool => collect($products)->firstWhere('name', 'Черновой блок')['has_published_version'] === false)
                ->missing('products.0.id'));
    }

    public function test_developer_cannot_reach_another_developers_or_platform_listings(): void
    {
        $foreign = MarketplaceListing::factory()->forProduct($this->publishedBlock(DeveloperProfile::factory()->create()))->create();
        $platform = MarketplaceListing::factory()->forProduct(BlockDefinition::factory()->platform()->create())->create();

        foreach ([$foreign, $platform] as $listing) {
            $this->actingAs($this->developer)->get(route('developer.marketplace.show', $listing->public_id))->assertNotFound();
            $this->actingAs($this->developer)->patch(route('developer.marketplace.update', $listing->public_id), ['title' => 'Захват'])->assertNotFound();
            $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $listing->public_id))->assertNotFound();
            $this->actingAs($this->developer)->post(route('developer.marketplace.unpublish', $listing->public_id))->assertNotFound();
        }

        $this->assertNotSame('Захват', $foreign->fresh()?->title);
        $this->assertSame(MarketplaceListingStatus::Draft, $foreign->fresh()?->status);
    }

    public function test_marketplace_requires_an_active_profile_with_the_marketplace_permission(): void
    {
        $block = $this->publishedBlock($this->profile);
        $listing = MarketplaceListing::factory()->forProduct($block)->create();

        $creatorOnly = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks, DeveloperPermission::CreateTemplates)->create()->user;
        $suspended = DeveloperProfile::factory()->withPermissions(DeveloperPermission::SubmitMarketplaceItem)->suspended()->create()->user;
        $workspaceOwner = User::factory()->create();
        Workspace::factory()->create()->addMember($workspaceOwner, WorkspaceRole::Owner);
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);

        foreach ([$creatorOnly, $suspended, $workspaceOwner, $superAdmin] as $user) {
            $this->actingAs($user)->get(route('developer.marketplace.index'))->assertForbidden();
            $this->actingAs($user)->post(route('developer.marketplace.store'), ['product_type' => 'block', 'product' => $block->public_id, 'title' => 'X', 'slug' => 'blocked'])->assertForbidden();
            $this->actingAs($user)->post(route('developer.marketplace.publish', $listing->public_id))->assertForbidden();
        }

        $this->profile->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->developer)->get(route('developer.marketplace.index'))->assertForbidden();
        $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $listing->public_id))->assertForbidden();

        $this->profile->forceFill(['status' => 'active'])->save();
        $this->actingAs($this->developer)->get(route('developer.marketplace.index'))->assertOk();
        $this->assertSame(1, MarketplaceListing::query()->count());
        $this->assertSame(MarketplaceListingStatus::Draft, $listing->fresh()?->status);
    }

    public function test_draft_listing_may_exist_before_the_product_version_but_publishes_only_after_it(): void
    {
        Log::spy();
        $block = BlockDefinition::factory()->developer($this->profile)->create();
        $this->store(['product_type' => 'block', 'product' => $block->public_id, 'title' => 'Ранняя карточка', 'slug' => 'early-card'])
            ->assertSessionHasNoErrors();
        $listing = MarketplaceListing::query()->sole();

        $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $listing->public_id))
            ->assertSessionHasErrors(['listing' => 'Сначала опубликуйте версию блока: черновик нельзя показать в Marketplace.']);
        $this->assertSame(MarketplaceListingStatus::Draft, $listing->fresh()?->status);
        $this->actingAs($this->developer)->get(route('developer.marketplace.show', $listing->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.has_published_version', false)
                ->where('listing.publication_denial', 'Сначала опубликуйте версию блока: черновик нельзя показать в Marketplace.'));

        BlockVersion::factory()->for($block, 'definition')->create();
        $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $listing->public_id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.marketplace.show', $listing->public_id));

        $published = $listing->fresh() ?? $listing;
        $this->assertSame(MarketplaceListingStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertTrue($published->isPubliclyVisible());
        Log::shouldHaveReceived('info')->with('developer.marketplace_listing_published', Mockery::on(fn (array $context): bool => $context['listing'] === $listing->public_id && $context['status'] === 'published'));
        $this->actingAs($this->developer)->get(route('developer.marketplace.show', $listing->public_id))
            ->assertInertia(fn (Assert $page) => $page->where('listing.status_label', 'Опубликовано')->where('listing.publicly_visible', true));

        $this->actingAs($this->developer)->post(route('developer.marketplace.unpublish', $listing->public_id))->assertSessionHasNoErrors();
        $unpublished = $listing->fresh() ?? $listing;
        $this->assertSame(MarketplaceListingStatus::Draft, $unpublished->status);
        $this->assertNull($unpublished->published_at);
        $this->assertFalse($unpublished->isPubliclyVisible());
        Log::shouldHaveReceived('info')->with('developer.marketplace_listing_unpublished', Mockery::any());
    }

    public function test_template_without_version_and_admin_grant_products_cannot_be_published(): void
    {
        $template = Template::factory()->developer($this->profile)->create();
        $granted = $this->publishedBlock($this->profile, ['access_mode' => CatalogAccessMode::AdminGrant]);
        $templateListing = MarketplaceListing::factory()->forProduct($template)->create();
        $grantedListing = MarketplaceListing::factory()->forProduct($granted)->create();

        $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $templateListing->public_id))
            ->assertSessionHasErrors(['listing' => 'Сначала опубликуйте версию шаблона: черновик нельзя показать в Marketplace.']);
        $this->actingAs($this->developer)->post(route('developer.marketplace.publish', $grantedListing->public_id))
            ->assertSessionHasErrors('listing');

        $this->assertSame(0, MarketplaceListing::query()->where('status', 'published')->count());
    }

    public function test_one_listing_per_product_and_globally_unique_slug(): void
    {
        $block = $this->publishedBlock($this->profile);
        $template = $this->publishedTemplate($this->profile);
        MarketplaceListing::factory()->forProduct($block)->create(['slug' => 'taken']);
        MarketplaceListing::factory()->forProduct($template)->create();

        $this->store(['product_type' => 'block', 'product' => $block->public_id, 'title' => 'Повтор', 'slug' => 'block-again'])
            ->assertSessionHasErrors(['product' => 'У этого блока уже есть карточка в Marketplace.']);
        $this->store(['product_type' => 'template', 'product' => $template->public_id, 'title' => 'Повтор', 'slug' => 'template-again'])
            ->assertSessionHasErrors(['product' => 'У этого шаблона уже есть карточка в Marketplace.']);

        $fresh = $this->publishedBlock($this->profile);
        $this->store(['product_type' => 'block', 'product' => $fresh->public_id, 'title' => 'Новая', 'slug' => 'taken'])
            ->assertSessionHasErrors(['slug' => 'Этот slug уже используется другой карточкой.']);
        $this->store(['product_type' => 'block', 'product' => $fresh->public_id, 'title' => 'Новая', 'slug' => 'Bad--Slug'])
            ->assertSessionHasErrors(['slug' => 'Slug может содержать только строчные латинские буквы, цифры и одиночные дефисы.']);

        $this->assertSame(2, MarketplaceListing::query()->count());
    }

    public function test_update_changes_plain_text_only(): void
    {
        Log::spy();
        $listing = MarketplaceListing::factory()->forProduct($this->publishedBlock($this->profile))->create(['slug' => 'stable-slug', 'title' => 'Старое']);

        $this->actingAs($this->developer)->patch(route('developer.marketplace.update', $listing->public_id), [
            'title' => 'Новое название',
            'description' => 'Описание без разметки',
            'slug' => 'changed-slug',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $updated = $listing->fresh() ?? $listing;
        $this->assertSame('Новое название', $updated->title);
        $this->assertSame('Описание без разметки', $updated->description);
        $this->assertSame('stable-slug', $updated->slug);
        $this->assertSame(MarketplaceListingStatus::Draft, $updated->status);
        $this->assertSame($this->developer->id, $updated->updated_by_user_id);
        Log::shouldHaveReceived('info')->with('developer.marketplace_listing_updated', Mockery::any());

        $this->actingAs($this->developer)->patch(route('developer.marketplace.update', $listing->public_id), [
            'title' => 'Название',
            'description' => '<script>alert(1)</script>',
        ])->assertSessionHasErrors(['description' => 'Описание — обычный текст, без HTML-разметки.']);
        $this->actingAs($this->developer)->patch(route('developer.marketplace.update', $listing->public_id), [
            'title' => str_repeat('я', MarketplaceListing::TITLE_MAX + 1),
        ])->assertSessionHasErrors('title');
        $this->assertSame('Описание без разметки', $listing->fresh()?->description);
    }

    public function test_dashboard_offers_marketplace_by_permission_without_moderation_wording(): void
    {
        $this->actingAs($this->developer)->get(route('developer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.submit_marketplace_item', true)
                ->where('capabilities.create_blocks', false));

        $this->assertSame('Публикация в Marketplace', DeveloperPermission::SubmitMarketplaceItem->label());
        $this->assertSame('Marketplace', DeveloperPermission::SubmitMarketplaceItem->shortLabel());

        foreach (['resources/js/pages/developer/dashboard.tsx', 'resources/js/pages/platform/developers/index.tsx'] as $path) {
            $this->assertDoesNotMatchRegularExpression('/модерац/iu', (string) file_get_contents(base_path($path)), $path);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function store(array $data): TestResponse
    {
        return $this->actingAs($this->developer)->post(route('developer.marketplace.store'), $data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedBlock(DeveloperProfile $profile, array $attributes = []): BlockDefinition
    {
        $block = BlockDefinition::factory()->developer($profile)->create($attributes);
        BlockVersion::factory()->for($block, 'definition')->create();

        return $block;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedTemplate(DeveloperProfile $profile, array $attributes = []): Template
    {
        return Template::factory()->developer($profile)->published()->create($attributes);
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
