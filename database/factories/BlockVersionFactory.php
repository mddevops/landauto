<?php

namespace Database\Factories;

use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockVersion>
 */
class BlockVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'block_definition_id' => BlockDefinition::factory(),
            'version' => '1.0.0',
            'schema_json' => ['fields' => []],
        ];
    }
}
