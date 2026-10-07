<?php

namespace App\Templates;

use App\Blocks\BlockReferenceResolver;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;

/**
 * References allowed from a Template Block: Pages of the same Template and scroll targets on the
 * same Page. Templates own no assets, vehicles or Popups.
 */
final readonly class TemplateBlockReferences implements BlockReferenceResolver
{
    public function __construct(private TemplatePage $page) {}

    public function existingAssets(array $ids): array
    {
        return [];
    }

    public function existingPages(array $ids): array
    {
        return TemplatePage::query()->where('template_id', $this->page->template_id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingBlocks(array $ids): array
    {
        return TemplateBlock::query()->where('template_page_id', $this->page->id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingVehicles(array $ids): array
    {
        return [];
    }

    public function existingPopups(array $ids): array
    {
        return [];
    }
}
