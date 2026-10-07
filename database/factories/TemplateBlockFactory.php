<?php

namespace Database\Factories;

use App\Models\BlockVersion;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateBlock>
 */
class TemplateBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_page_id' => TemplatePage::factory(),
            'block_version_id' => BlockVersion::factory(),
            'sort_order' => 0,
            'state_json' => [],
        ];
    }
}
