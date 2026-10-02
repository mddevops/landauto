<?php

namespace Database\Seeders;

use App\Blocks\BlockSchemaValidator;
use App\Blocks\OfficialBlockCatalog;
use App\Models\BlockDefinition;
use Illuminate\Database\Seeder;

class OfficialBlockSeeder extends Seeder
{
    public function run(BlockSchemaValidator $validator): void
    {
        foreach (OfficialBlockCatalog::blocks() as $block) {
            // Seeders may run without model events, which would skip the Block Version hook.
            $validator->assertValid($block['schema']);

            $definition = BlockDefinition::query()->firstOrNew(['slug' => $block['slug']]);
            $definition->forceFill(['name' => $block['name'], 'is_official' => true])->save();
            $definition->versions()->firstOrCreate(
                ['version' => $block['version']],
                ['schema_json' => $block['schema']],
            );
        }
    }
}
