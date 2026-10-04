<?php

namespace Database\Factories;

use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockInstance>
 */
class BlockInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'block_version_id' => BlockVersion::factory(),
            'sort_order' => 0,
            'state_json' => [],
        ];
    }
}
