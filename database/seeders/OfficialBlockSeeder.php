<?php

namespace Database\Seeders;

use App\Blocks\BlockSchemaValidator;
use App\Blocks\OfficialBlockCatalog;
use App\Enums\BlockOwnerScope;
use App\Models\BlockDefinition;
use Illuminate\Database\Seeder;

class OfficialBlockSeeder extends Seeder
{
    public function run(BlockSchemaValidator $validator): void
    {
        foreach (OfficialBlockCatalog::blocks() as $block) {
            // Seeders may run without model events, which would skip the Block Version hook.
            $validator->assertValid($block['schema']);

            // A slug held by a non-platform Block fails loudly instead of changing its owner.
            $definition = BlockDefinition::query()->firstOrNew(['slug' => $block['slug']]);
            $definition->forceFill(['name' => $block['name'], 'owner_scope' => BlockOwnerScope::Platform])->save();
            $definition->versions()->firstOrCreate(
                ['version' => $block['version']],
                ['schema_json' => $block['schema']],
            );
        }
    }
}
