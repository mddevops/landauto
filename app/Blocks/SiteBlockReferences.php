<?php

namespace App\Blocks;

use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteComponent;
use App\Models\SiteVehicle;

/**
 * References allowed from a Site Component: assets, pages, vehicles and popups of the Site,
 * plus any Block Instance or Site Component of that Site as a scroll target.
 */
final readonly class SiteBlockReferences implements BlockReferenceResolver
{
    public function __construct(private Site $site) {}

    public function existingAssets(array $ids): array
    {
        return SiteAsset::query()->where('site_id', $this->site->id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingPages(array $ids): array
    {
        return Page::query()->where('site_id', $this->site->id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingBlocks(array $ids): array
    {
        $pageBlocks = BlockInstance::query()
            ->whereIn('page_id', Page::query()->where('site_id', $this->site->id)->select('id'))
            ->whereIn('public_id', $ids)
            ->pluck('public_id');
        $components = SiteComponent::query()
            ->where('site_id', $this->site->id)
            ->whereIn('public_id', $ids)
            ->pluck('public_id');

        return array_values(array_unique([...$pageBlocks->all(), ...$components->all()]));
    }

    public function existingVehicles(array $ids): array
    {
        return SiteVehicle::query()->where('site_id', $this->site->id)->whereIn('public_id', $ids)->pluck('public_id')->all();
    }

    public function existingPopups(array $ids): array
    {
        return Popup::query()->where('site_id', $this->site->id)->active()->whereIn('public_id', $ids)->pluck('public_id')->all();
    }
}
