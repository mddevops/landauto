<?php

namespace Tests\Feature\Marketplace;

use App\Enums\DeveloperPermission;
use App\Enums\MarketplaceListingStatus;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\PlatformRoleAssignment;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class PlatformMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);
    }

    public function test_super_admin_lists_platform_block_and_template_without_a_developer_profile(): void
    {
        Log::spy();
        $block = BlockDefinition::factory()->platform()->create(['name' => 'Первый экран']);
        BlockVersion::factory()->for($block, 'definition')->create();
        $template = Template::factory()->published()->create(['name' => 'Официальный лендинг']);

        $this->actingAs($this->superAdmin)->get(route('platform.marketplace.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/marketplace/index')
                ->has('listings', 0)
                ->where('products', fn ($products): bool => collect($products)->pluck('name')->sort()->values()->all() === ['Официальный лендинг', 'Первый экран']));

        $this->actingAs($this->superAdmin)->post(route('platform.marketplace.store'), [
            'product_type' => 'block', 'product' => $block->public_id, 'title' => 'Первый экран Landflow', 'slug' => 'landflow-hero',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->superAdmin)->post(route('platform.marketplace.store'), [
            'product_type' => 'template', 'product' => $template->public_id, 'title' => 'Лендинг Landflow', 'slug' => 'landflow-landing',
        ])->assertSessionHasNoErrors();

        $blockListing = MarketplaceListing::query()->where('slug', 'landflow-hero')->sole();
        $templateListing = MarketplaceListing::query()->where('slug', 'landflow-landing')->sole();
        $this->assertNull($blockListing->developer_profile_id);
        $this->assertNull($templateListing->developer_profile_id);
        $this->assertNull($this->superAdmin->developerProfile);
        Log::shouldHaveReceived('info')->with('platform.marketplace_listing_created', Mockery::on(fn (array $context): bool => $context === [
            'listing' => $blockListing->public_id,
            'product_type' => 'block',
            'block' => $block->public_id,
            'status' => 'draft',
            'actor_user_id' => $this->superAdmin->id,
        ]));

        $this->actingAs($this->superAdmin)->get(route('platform.marketplace.show', $templateListing->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/marketplace/show')
                ->where('listing.author', 'Landflow')
                ->where('listing.product_type_label', 'Шаблон')
                ->missing('listing.id')
                ->missing('listing.template_id'));

        $this->actingAs($this->superAdmin)->post(route('platform.marketplace.publish', $templateListing->public_id))->assertSessionHasNoErrors();
        $this->assertSame(MarketplaceListingStatus::Published, $templateListing->fresh()?->status);
        $this->assertTrue($templateListing->fresh()?->isPubliclyVisible());
        Log::shouldHaveReceived('info')->with('platform.marketplace_listing_published', Mockery::any());

        $this->actingAs($this->superAdmin)->patch(route('platform.marketplace.update', $templateListing->public_id), ['title' => 'Лендинг от Landflow'])->assertSessionHasNoErrors();
        Log::shouldHaveReceived('info')->with('platform.marketplace_listing_updated', Mockery::any());
        $this->actingAs($this->superAdmin)->post(route('platform.marketplace.unpublish', $templateListing->public_id))->assertSessionHasNoErrors();
        $this->assertSame(MarketplaceListingStatus::Draft, $templateListing->fresh()?->status);
        Log::shouldHaveReceived('info')->with('platform.marketplace_listing_unpublished', Mockery::any());
    }

    public function test_platform_surface_cannot_take_over_developer_products_or_listings(): void
    {
        $profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::SubmitMarketplaceItem)->create();
        $developerBlock = BlockDefinition::factory()->developer($profile)->create();
        BlockVersion::factory()->for($developerBlock, 'definition')->create();
        $developerTemplate = Template::factory()->developer($profile)->published()->create();
        $privateBlock = BlockDefinition::factory()->workspacePrivate()->create();
        $developerListing = MarketplaceListing::factory()->forProduct($developerBlock)->create(['title' => 'Карточка разработчика']);

        foreach ([['block', $developerBlock->public_id], ['template', $developerTemplate->public_id], ['block', $privateBlock->public_id]] as $index => [$type, $product]) {
            $this->actingAs($this->superAdmin)->post(route('platform.marketplace.store'), [
                'product_type' => $type, 'product' => $product, 'title' => 'Захват', 'slug' => "takeover-{$index}",
            ])->assertSessionHasErrors('product');
        }

        $this->actingAs($this->superAdmin)->get(route('platform.marketplace.index'))
            ->assertInertia(fn (Assert $page) => $page->has('listings', 0)->has('products', 0));
        $this->actingAs($this->superAdmin)->get(route('platform.marketplace.show', $developerListing->public_id))->assertNotFound();
        $this->actingAs($this->superAdmin)->patch(route('platform.marketplace.update', $developerListing->public_id), ['title' => 'Захват'])->assertNotFound();
        $this->actingAs($this->superAdmin)->post(route('platform.marketplace.publish', $developerListing->public_id))->assertNotFound();

        $this->assertSame(1, MarketplaceListing::query()->count());
        $this->assertSame('Карточка разработчика', $developerListing->fresh()?->title);
        $this->assertSame($profile->id, $developerListing->fresh()?->developer_profile_id);
    }

    public function test_only_manage_platform_content_reaches_official_listings(): void
    {
        $listing = MarketplaceListing::factory()->create();
        $catalogManager = $this->userWithRole(PlatformRole::CatalogManager);
        $developer = DeveloperProfile::factory()->withPermissions()->create()->user;

        foreach ([$catalogManager, $developer, User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('platform.marketplace.index'))->assertForbidden();
            $this->actingAs($user)->post(route('platform.marketplace.store'), ['product_type' => 'block', 'product' => 'x', 'title' => 'X', 'slug' => 'nope'])->assertForbidden();
            $this->actingAs($user)->get(route('platform.marketplace.show', $listing->public_id))->assertForbidden();
            $this->actingAs($user)->post(route('platform.marketplace.publish', $listing->public_id))->assertForbidden();
        }
    }

    public function test_there_is_no_public_marketplace_storefront_yet(): void
    {
        foreach (['marketplace.index', 'marketplace.show', 'marketplace.categories', 'marketplace.search'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }

        $this->get(route('platform.marketplace.index'))->assertRedirect(route('login'));
        $this->get('/marketplace')->assertNotFound();
        $this->actingAs($this->superAdmin)->get('/marketplace')->assertNotFound();
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
