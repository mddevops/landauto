<?php

namespace Database\Seeders;

use App\Blocks\BlockSchemaValidator;
use App\Blocks\OfficialBlockCatalog;
use App\Enums\BlockOwnerScope;
use App\Models\BlockDefinition;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * The catalog bootstraps missing official Block Definitions and appends missing immutable versions.
 * Existing Definition metadata is edited through Platform Block Authoring and is never overwritten.
 */
class OfficialBlockSeeder extends Seeder
{
    public function run(BlockSchemaValidator $validator): void
    {
        foreach (OfficialBlockCatalog::blocks() as $block) {
            // Seeders may run without model events, which would skip the Block Version hook.
            $validator->assertValid($block['schema']);

            $definition = BlockDefinition::query()->where('slug', $block['slug'])->first();

            if ($definition === null) {
                $definition = new BlockDefinition([
                    'slug' => $block['slug'],
                    'name' => $block['name'],
                    'category' => OfficialBlockCatalog::category($block['slug']),
                ]);
                $definition->owner_scope = BlockOwnerScope::Platform;
                $definition->save();
            } elseif (! $definition->isPlatformOwned()) {
                throw new LogicException("Official Block slug [{$block['slug']}] is held by a non-platform Block.");
            }

            $definition->versions()->firstOrCreate(
                ['version' => $block['version']],
                ['schema_json' => $block['schema']],
            );
        }
    }
}
