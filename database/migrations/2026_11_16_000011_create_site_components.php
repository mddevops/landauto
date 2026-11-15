<?php

use App\Enums\ComponentPosition;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Site;
use App\Models\SiteComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_definitions', function (Blueprint $table) {
            $table->boolean('is_component')->default(false)->after('category');
            $table->string('component_position', 16)->nullable()->after('is_component');
        });

        BlockDefinition::query()->where('slug', 'header')->update([
            'is_component' => true,
            'component_position' => ComponentPosition::First->value,
        ]);
        BlockDefinition::query()->where('slug', 'footer')->update([
            'is_component' => true,
            'component_position' => ComponentPosition::Last->value,
        ]);

        Schema::create('site_components', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->json('state_json');
            $table->timestamps();

            $table->index(['site_id', 'sort_order']);
        });

        $this->migrateExistingSites();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_components');
        Schema::table('block_definitions', function (Blueprint $table) {
            $table->dropColumn(['is_component', 'component_position']);
        });
    }

    private function migrateExistingSites(): void
    {
        $componentDefinitionIds = BlockDefinition::query()
            ->where('is_component', true)
            ->pluck('id')
            ->all();

        if ($componentDefinitionIds === []) {
            return;
        }

        foreach (Site::query()->orderBy('id')->cursor() as $site) {
            $home = $site->pages()->where('is_home', true)->first();
            $seenDefinitions = [];

            $candidates = $home?->blocks()
                ->with('version.definition')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get() ?? collect();

            foreach ($candidates as $block) {
                /** @var BlockInstance $block */
                $definition = $block->version->definition;

                if (! $definition->is_component || isset($seenDefinitions[$definition->id])) {
                    continue;
                }

                $component = new SiteComponent([
                    'sort_order' => $definition->component_position === ComponentPosition::Free
                        ? count($seenDefinitions)
                        : 0,
                    'state_json' => $block->state_json,
                ]);
                $component->public_id = $block->public_id;
                $component->is_hidden = $block->is_hidden;
                $component->site()->associate($site);
                $component->version()->associate($block->version);
                $component->saveQuietly();
                $seenDefinitions[$definition->id] = true;
            }

            BlockInstance::query()
                ->whereIn('page_id', $site->pages()->select('id'))
                ->whereIn('block_version_id', function ($query) use ($componentDefinitionIds): void {
                    $query->select('id')
                        ->from('block_versions')
                        ->whereIn('block_definition_id', $componentDefinitionIds);
                })
                ->delete();
        }
    }
};
