<?php

namespace Tests\Feature\Marketplace;

use App\Enums\CatalogAccessMode;
use App\Enums\MarketplaceListingStatus;
use App\Enums\MarketplaceProductType;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class MarketplaceListingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_and_template_listings_reference_exactly_their_product_and_its_owner(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $block = BlockDefinition::factory()->developer($profile)->create();
        $template = Template::factory()->developer($profile)->create();

        $blockListing = MarketplaceListing::factory()->forProduct($block)->create();
        $templateListing = MarketplaceListing::factory()->forProduct($template)->create();
        $platformListing = MarketplaceListing::factory()->forProduct(Template::factory()->create())->create();

        $this->assertSame(MarketplaceProductType::Block, $blockListing->product_type);
        $this->assertSame($block->id, $blockListing->block_definition_id);
        $this->assertNull($blockListing->template_id);
        $this->assertSame($profile->id, $blockListing->developer_profile_id);
        $this->assertTrue($blockListing->product()?->is($block));

        $this->assertSame(MarketplaceProductType::Template, $templateListing->product_type);
        $this->assertSame($template->id, $templateListing->template_id);
        $this->assertNull($templateListing->block_definition_id);
        $this->assertSame($profile->id, $templateListing->developer_profile_id);

        $this->assertNull($platformListing->developer_profile_id);
        $this->assertTrue($platformListing->isPlatformOwned());
        $this->assertSame(MarketplaceListingStatus::Draft, $platformListing->fresh()?->status);
        $this->assertTrue($block->marketplaceListing()->sole()->is($blockListing));
    }

    public function test_listing_needs_exactly_one_product_matching_its_type(): void
    {
        $block = BlockDefinition::factory()->create();
        $template = Template::factory()->create();

        $invalid = [
            'both' => ['product_type' => MarketplaceProductType::Block, 'block_definition_id' => $block->id, 'template_id' => $template->id],
            'neither' => ['product_type' => MarketplaceProductType::Block, 'block_definition_id' => null, 'template_id' => null],
            'type mismatch' => ['product_type' => MarketplaceProductType::Template, 'block_definition_id' => $block->id, 'template_id' => null],
        ];

        foreach ($invalid as $case => $attributes) {
            try {
                MarketplaceListing::factory()->create($attributes);
                $this->fail("Listing with {$case} product reference was saved.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('exactly one existing product', $exception->getMessage());
            }
        }

        $this->assertSame(0, MarketplaceListing::query()->count());
    }

    public function test_owner_always_matches_the_product_owner(): void
    {
        $owner = DeveloperProfile::factory()->create();
        $other = DeveloperProfile::factory()->create();
        $developerBlock = BlockDefinition::factory()->developer($owner)->create();
        $platformBlock = BlockDefinition::factory()->platform()->create();

        $cases = [
            [$developerBlock, $other->id],
            [$developerBlock, null],
            [$platformBlock, $owner->id],
        ];

        foreach ($cases as [$product, $developerProfileId]) {
            try {
                MarketplaceListing::factory()->forProduct($product)->create(['developer_profile_id' => $developerProfileId]);
                $this->fail('Listing with a foreign owner was saved.');
            } catch (LogicException $exception) {
                $this->assertSame('Marketplace Listing owner must match the owner of its product.', $exception->getMessage());
            }
        }

        $this->assertSame(0, MarketplaceListing::query()->count());
    }

    public function test_workspace_private_blocks_cannot_be_listed(): void
    {
        $private = BlockDefinition::factory()->workspacePrivate()->create();

        $this->expectExceptionObject(new LogicException('Workspace-private Blocks cannot have a Marketplace Listing.'));

        MarketplaceListing::factory()->forProduct($private)->create();
    }

    public function test_identity_product_owner_and_slug_are_immutable(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $listing = MarketplaceListing::factory()->forProduct(BlockDefinition::factory()->developer($profile)->create())->create(['created_by_user_id' => User::factory()->create()->id]);
        $otherBlock = BlockDefinition::factory()->developer($profile)->create();

        $changes = [
            'public_id' => (string) Str::ulid(),
            'slug' => 'renamed-listing',
            'block_definition_id' => $otherBlock->id,
            'created_by_user_id' => User::factory()->create()->id,
        ];

        foreach ($changes as $attribute => $value) {
            $fresh = $listing->fresh() ?? $listing;
            $fresh->setAttribute($attribute, $value);

            try {
                $fresh->save();
                $this->fail("{$attribute} changed.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }

        $retargeted = $listing->fresh() ?? $listing;
        $retargeted->product_type = MarketplaceProductType::Template;
        $retargeted->block_definition_id = null;
        $retargeted->template_id = Template::factory()->developer($profile)->create()->id;
        $this->expectException(LogicException::class);
        $retargeted->save();
    }

    public function test_database_allows_one_listing_per_product_and_a_globally_unique_slug(): void
    {
        $block = BlockDefinition::factory()->create();
        $template = Template::factory()->create();
        MarketplaceListing::factory()->forProduct($block)->create(['slug' => 'taken-slug']);
        MarketplaceListing::factory()->forProduct($template)->create();

        foreach ([
            fn () => MarketplaceListing::factory()->forProduct($block)->create(),
            fn () => MarketplaceListing::factory()->forProduct($template)->create(),
            fn () => MarketplaceListing::factory()->forProduct(BlockDefinition::factory()->create())->create(['slug' => 'taken-slug']),
        ] as $duplicate) {
            try {
                $duplicate();
                $this->fail('Duplicate listing was saved.');
            } catch (UniqueConstraintViolationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, MarketplaceListing::query()->count());
    }

    public function test_published_listing_needs_a_published_version_public_access_and_published_at(): void
    {
        $draftOnly = BlockDefinition::factory()->create();
        $granted = BlockDefinition::factory()->create(['access_mode' => CatalogAccessMode::AdminGrant]);
        BlockVersion::factory()->for($granted, 'definition')->create();
        $ready = BlockDefinition::factory()->create();
        BlockVersion::factory()->for($ready, 'definition')->create();

        foreach ([$draftOnly, $granted] as $product) {
            try {
                MarketplaceListing::factory()->forProduct($product)->published()->create();
                $this->fail('Listing was published without a public published product.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('published version and public access', $exception->getMessage());
            }
        }

        try {
            MarketplaceListing::factory()->forProduct($ready)->create(['published_at' => now()]);
            $this->fail('Draft listing with published_at was saved.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('published_at', $exception->getMessage());
        }

        $published = MarketplaceListing::factory()->forProduct($ready)->published()->create();
        $this->assertTrue($published->isPublished());
        $this->assertNotNull($published->published_at);
    }

    public function test_schema_has_no_commercial_version_or_review_state(): void
    {
        $this->assertTrue(Schema::hasColumns('marketplace_listings', [
            'public_id', 'product_type', 'block_definition_id', 'template_id', 'developer_profile_id',
            'title', 'slug', 'description', 'status', 'published_at', 'created_by_user_id', 'updated_by_user_id',
        ]));

        foreach (['price', 'pricing_type', 'currency', 'site_price_minor', 'workspace_price_minor', 'price_currency',
            'access_mode', 'access_entitlement', 'block_version_id', 'template_version_id', 'deleted_at'] as $column) {
            $this->assertFalse(Schema::hasColumn('marketplace_listings', $column), "marketplace_listings.{$column} must not exist.");
        }

        $this->assertFalse(Schema::hasTable('marketplace_reviews'));
        $this->assertSame(['draft', 'published'], array_column(MarketplaceListingStatus::cases(), 'value'));
        $this->assertSame(['block', 'template'], array_column(MarketplaceProductType::cases(), 'value'));
    }

    public function test_listed_products_and_developer_profiles_cannot_be_deleted(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $block = BlockDefinition::factory()->developer($profile)->create();
        MarketplaceListing::factory()->forProduct($block)->create();

        foreach ([fn () => $block->delete(), fn () => $profile->delete()] as $delete) {
            try {
                $delete();
                $this->fail('A listed product or its Developer Profile was deleted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, MarketplaceListing::query()->count());
    }
}
