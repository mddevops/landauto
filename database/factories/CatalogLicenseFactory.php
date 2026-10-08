<?php

namespace Database\Factories;

use App\Enums\CatalogLicenseScope;
use App\Enums\CatalogLicenseSource;
use App\Models\BlockDefinition;
use App\Models\CatalogLicense;
use App\Models\Site;
use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogLicense>
 */
class CatalogLicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scope' => CatalogLicenseScope::Site,
            'site_id' => Site::factory(),
            'workspace_id' => null,
            'block_definition_id' => BlockDefinition::factory(),
            'template_id' => null,
            'source' => CatalogLicenseSource::AdminGrant,
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(['scope' => CatalogLicenseScope::Site, 'site_id' => $site->id, 'workspace_id' => null]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(['scope' => CatalogLicenseScope::Workspace, 'site_id' => null, 'workspace_id' => $workspace->id]);
    }

    public function ofBlock(BlockDefinition $block): static
    {
        return $this->state(['block_definition_id' => $block->id, 'template_id' => null]);
    }

    public function ofTemplate(Template $template): static
    {
        return $this->state(['block_definition_id' => null, 'template_id' => $template->id]);
    }
}
