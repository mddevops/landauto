<?php

namespace Database\Factories;

use App\Enums\BlockRuntime;
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

    /**
     * A version published from Block Studio (ADR-008).
     */
    public function sandboxed(string $html = '<p>Блок</p>', string $css = '', string $js = ''): static
    {
        return $this->state(fn (): array => [
            'runtime' => BlockRuntime::Sandboxed,
            'html' => $html,
            'css' => $css,
            'js' => $js,
        ]);
    }

    /**
     * A Native version (ADR-009). No production path creates these until the D-123 approval flow
     * exists (X-025); X-024 tests and fixtures use this state. Native JavaScript stays empty by
     * default because non-empty Native JavaScript blocks Site publishing.
     */
    public function native(string $html = '<p>Блок</p>', string $css = '', string $js = ''): static
    {
        return $this->state(fn (): array => [
            'runtime' => BlockRuntime::Native,
            'html' => $html,
            'css' => $css,
            'js' => $js,
        ]);
    }
}
