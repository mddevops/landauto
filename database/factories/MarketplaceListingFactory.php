<?php

namespace Database\Factories;

use App\Enums\MarketplaceListingStatus;
use App\Enums\MarketplaceProductType;
use App\Models\BlockDefinition;
use App\Models\MarketplaceListing;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a Draft listing of a new platform Block; `forProduct` derives the owner like the app.
 *
 * @extends Factory<MarketplaceListing>
 */
class MarketplaceListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_type' => MarketplaceProductType::Block,
            'block_definition_id' => BlockDefinition::factory()->platform(),
            'template_id' => null,
            'developer_profile_id' => null,
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->regexify('[a-z]{5,10}-[a-z0-9]{5}'),
            'description' => null,
            'status' => MarketplaceListingStatus::Draft,
            'published_at' => null,
        ];
    }

    public function forProduct(BlockDefinition|Template $product): static
    {
        return $this->state([
            'product_type' => $product instanceof Template ? MarketplaceProductType::Template : MarketplaceProductType::Block,
            'block_definition_id' => $product instanceof BlockDefinition ? $product->id : null,
            'template_id' => $product instanceof Template ? $product->id : null,
            'developer_profile_id' => $product->developer_profile_id,
        ]);
    }

    public function published(): static
    {
        return $this->state(['status' => MarketplaceListingStatus::Published, 'published_at' => now()]);
    }
}
