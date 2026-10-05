<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\PublishedAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Models\PublishedVersion;
use App\Models\SeriesMediaImage;
use App\Models\Site;
use App\Models\SiteAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Version-scoped public file delivery (ADR-006 §4). A file is served only when a ready Published
 * Version of this host's Site references it; Draft-only Site Assets and unreferenced Series Media
 * stay private. Historical versions keep their files.
 */
class PublishedAssetController extends Controller
{
    public function asset(Request $request, string $version, string $asset): Response
    {
        $site = ResolvePublicSite::site($request);

        if (! $this->references($site, $version, PublishedAssetKind::SiteAsset, $asset)) {
            return ResolvePublicSite::notFound();
        }

        $file = SiteAsset::query()->where('site_id', $site->id)->where('public_id', strtolower($asset))->first();

        return $file === null ? ResolvePublicSite::notFound() : $this->serve(SiteAsset::DISK, $file->path, $file->mime_type);
    }

    public function media(Request $request, string $version, string $image): Response
    {
        $site = ResolvePublicSite::site($request);

        if (! $this->references($site, $version, PublishedAssetKind::SeriesMediaImage, $image)) {
            return ResolvePublicSite::notFound();
        }

        $file = SeriesMediaImage::query()->where('public_id', strtolower($image))->first();

        return $file === null ? ResolvePublicSite::notFound() : $this->serve(SeriesMediaImage::DISK, $file->path, $file->mime_type);
    }

    private function references(Site $site, string $version, PublishedAssetKind $kind, string $publicId): bool
    {
        return PublishedVersion::query()
            ->where('site_id', $site->id)
            ->where('public_id', strtolower($version))
            ->where('status', PublishedVersionStatus::Ready->value)
            ->whereHas('assetReferences', fn ($query) => $query->where('kind', $kind->value)->where('reference_public_id', strtolower($publicId)))
            ->exists();
    }

    private function serve(string $disk, string $path, string $mime): Response
    {
        if (! Storage::disk($disk)->exists($path)) {
            return ResolvePublicSite::notFound();
        }

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
