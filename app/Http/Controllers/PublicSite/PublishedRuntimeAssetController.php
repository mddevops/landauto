<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\PublishedRuntimeAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Models\PublishedRuntimeAsset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the compiled Native CSS of a ready Published Version of this host's Site (ADR-009).
 * The content hash is part of the URL, so the response is immutable; a wrong hash, a Draft-only
 * or foreign version is a safe 404. Nothing is compiled per request.
 */
class PublishedRuntimeAssetController extends Controller
{
    public function stylesheet(Request $request, string $version, string $hash): Response
    {
        $site = ResolvePublicSite::site($request);

        $asset = PublishedRuntimeAsset::query()
            ->where('kind', PublishedRuntimeAssetKind::NativeCss->value)
            ->where('content_hash', $hash)
            ->whereHas('version', fn ($query) => $query
                ->where('site_id', $site->id)
                ->where('public_id', strtolower($version))
                ->where('status', PublishedVersionStatus::Ready->value))
            ->first(['content']);

        if ($asset === null) {
            return ResolvePublicSite::notFound();
        }

        return response($asset->content, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
