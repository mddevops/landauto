<?php

namespace Tests\Feature\Marketplace;

use App\Blocks\BlockAuthoring;
use App\Enums\CatalogAccessMode;
use App\Enums\DeveloperPermission;
use App\Enums\DeveloperProfileStatus;
use App\Enums\MarketplaceListingStatus;
use App\Enums\PlatformRole;
use App\Marketplace\MarketplaceListingPresenter;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\PlatformRoleAssignment;
use App\Models\Template;
use App\Models\User;
use App\Templates\TemplateAuthoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * `MarketplaceListing::publiclyVisible()` is the single, fail-closed predicate for the future
 * storefront (P10-002); commercial data always comes from the canonical product (D-121).
 */
class MarketplaceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_valid_published_listings_are_publicly_visible(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $developerListing = MarketplaceListing::factory()->forProduct($this->publishedBlock($profile))->published()->create();
        $platformListing = MarketplaceListing::factory()->forProduct(Template::factory()->published()->create())->published()->create();
        $draftListing = MarketplaceListing::factory()->forProduct($this->publishedBlock($profile))->create();

        $this->assertVisible([$developerListing, $platformListing]);
        $this->assertFalse($draftListing->isPubliclyVisible());
    }

    public function test_suspended_developer_listings_are_hidden_until_reactivation_and_stay_intact(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $listing = MarketplaceListing::factory()->forProduct($this->publishedBlock($profile))->published()->create();
        $platformListing = MarketplaceListing::factory()->forProduct($this->publishedBlock(null))->published()->create();

        $profile->forceFill(['status' => DeveloperProfileStatus::Suspended])->save();
        $this->assertVisible([$platformListing]);
        $stored = $listing->fresh() ?? $listing;
        $this->assertSame(MarketplaceListingStatus::Published, $stored->status);
        $this->assertSame($profile->id, $stored->developer_profile_id);

        $profile->forceFill(['status' => DeveloperProfileStatus::Active])->save();
        $this->assertVisible([$listing, $platformListing]);
    }

    public function test_switching_a_product_to_admin_grant_hides_the_listing_without_deleting_it(): void
    {
        $block = $this->publishedBlock(DeveloperProfile::factory()->create());
        $template = Template::factory()->published()->create();
        $blockListing = MarketplaceListing::factory()->forProduct($block)->published()->create();
        $templateListing = MarketplaceListing::factory()->forProduct($template)->published()->create();

        $block->access_mode = CatalogAccessMode::AdminGrant;
        $block->save();
        $template->access_mode = CatalogAccessMode::AdminGrant;
        $template->save();

        $this->assertVisible([]);
        $this->assertSame(2, MarketplaceListing::query()->where('status', 'published')->count());
        $this->assertNotNull($blockListing->fresh());
        $this->assertNotNull($templateListing->fresh());

        $block->access_mode = CatalogAccessMode::Free;
        $block->save();
        $this->assertVisible([$blockListing]);
    }

    public function test_inconsistent_rows_fail_closed(): void
    {
        $versionless = MarketplaceListing::factory()->forProduct(BlockDefinition::factory()->create())->create();
        $private = BlockDefinition::factory()->workspacePrivate()->create();
        BlockVersion::factory()->for($private, 'definition')->create();
        $carrier = MarketplaceListing::factory()->forProduct($this->publishedBlock(null))->create();

        // Bypass the model on purpose: even corrupted rows never become public.
        DB::table('marketplace_listings')->where('id', $versionless->id)->update(['status' => 'published', 'published_at' => now()]);
        DB::table('marketplace_listings')->where('id', $carrier->id)->update(['status' => 'published', 'published_at' => now(), 'block_definition_id' => $private->id]);

        $this->assertVisible([]);
    }

    public function test_listing_reads_prices_from_the_canonical_block_and_template(): void
    {
        $superAdmin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $superAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks, DeveloperPermission::SubmitMarketplaceItem)->create();
        $developer = $profile->user;

        $block = $this->publishedBlock($profile);
        app(BlockAuthoring::class)->updateAccess($developer, $block, CatalogAccessMode::Paid, null, 490_000, 1_490_000);
        $listing = MarketplaceListing::factory()->forProduct($block)->published()->create();
        $stamp = $listing->fresh()?->updated_at?->toIso8601String();

        $this->actingAs($developer)->get(route('developer.marketplace.show', $listing->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.pricing', ['site_price_minor' => 490_000, 'workspace_price_minor' => 1_490_000, 'currency' => 'RUB'])
                ->where('listing.access.label', 'Платно')
                ->where('listing.access.detail', fn (string $detail): bool => str_contains($detail, 'Лицензия на 1 сайт') && str_contains($detail, "4\u{00A0}900")));

        $this->travel(1)->minute();
        app(BlockAuthoring::class)->updateAccess($developer, $block->fresh() ?? $block, CatalogAccessMode::Paid, null, 990_000, null);

        $this->actingAs($developer)->get(route('developer.marketplace.show', $listing->public_id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.pricing', ['site_price_minor' => 990_000, 'workspace_price_minor' => null, 'currency' => 'RUB'])
                ->where('listing.access.detail', fn (string $detail): bool => str_contains($detail, "9\u{00A0}900") && ! str_contains($detail, 'всё пространство')));
        $this->assertSame($stamp, $listing->fresh()?->updated_at?->toIso8601String());

        $template = Template::factory()->published()->create();
        app(TemplateAuthoring::class)->updateAccess($superAdmin, $template, CatalogAccessMode::Paid, null, null, 2_500_000);
        $templateListing = MarketplaceListing::factory()->forProduct($template)->create();
        $detail = app(MarketplaceListingPresenter::class)->detail($templateListing->fresh() ?? $templateListing);
        $this->assertSame(['site_price_minor' => null, 'workspace_price_minor' => 2_500_000, 'currency' => 'RUB'], $detail['pricing']);
        $this->assertSame('Landflow', $detail['author']);
    }

    /**
     * @param  list<MarketplaceListing>  $expected
     */
    private function assertVisible(array $expected): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (MarketplaceListing $listing): string => $listing->public_id, $expected),
            MarketplaceListing::query()->publiclyVisible()->pluck('public_id')->all(),
        );
    }

    private function publishedBlock(?DeveloperProfile $profile): BlockDefinition
    {
        $factory = BlockDefinition::factory();
        $block = ($profile === null ? $factory->platform() : $factory->developer($profile))->create();
        BlockVersion::factory()->for($block, 'definition')->create();

        return $block;
    }
}
