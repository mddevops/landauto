<?php

namespace App\Blocks;

use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Popup;
use App\Models\SiteAsset;
use App\Models\SiteComponent;
use App\Models\SiteVehicle;

/**
 * References allowed from a Block on a given Page: assets, pages, vehicles and active Popups of
 * the same Site, and scroll targets on the same Page.
 */
final readonly class PageBlockReferences implements BlockReferenceResolver
{
    public function __construct(private Page $page) {}

    public function existingAssets(array $ids): array
    {
        return SiteAsset::query()->where('site_id', $this->page->site_id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingPages(array $ids): array
    {
        return Page::query()->where('site_id', $this->page->site_id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingBlocks(array $ids): array
    {
        $pageBlocks = BlockInstance::query()
            ->where('page_id', $this->page->id)
            ->whereIn('public_id', $ids)
            ->pluck('public_id');
        $components = SiteComponent::query()
            ->where('site_id', $this->page->site_id)
            ->whereIn('public_id', $ids)
            ->pluck('public_id');

        return array_values(array_unique([...$pageBlocks->all(), ...$components->all()]));
    }

    public function existingVehicles(array $ids): array
    {
        return SiteVehicle::query()->where('site_id', $this->page->site_id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingPopups(array $ids): array
    {
        return Popup::query()->where('site_id', $this->page->site_id)->active()->whereIn('public_id', $ids)->pluck('public_id')->all();
    }
}
