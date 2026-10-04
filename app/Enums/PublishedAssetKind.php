<?php

namespace App\Enums;

enum PublishedAssetKind: string
{
    case SiteAsset = 'site_asset';
    case SeriesMediaImage = 'series_media_image';
}
