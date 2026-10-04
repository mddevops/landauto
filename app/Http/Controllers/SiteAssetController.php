<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadSiteAssetRequest;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SiteAssetController extends Controller
{
    public function store(UploadSiteAssetRequest $request, Site $site): RedirectResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);
        $mime = (string) $file->getMimeType();
        $info = getimagesize((string) $file->getRealPath());
        abort_if($info === false, 422);
        [$width, $height] = $info;

        $asset = new SiteAsset(['original_name' => Str::limit(trim($file->getClientOriginalName()) ?: 'image', 250, '')]);
        $asset->public_id = $asset->newUniqueId();
        $asset->path = "site-assets/{$site->public_id}/{$asset->public_id}.".SiteAsset::TYPES[$mime];
        $asset->mime_type = $mime;
        $asset->size_bytes = (int) $file->getSize();
        $asset->width = (int) $width;
        $asset->height = (int) $height;
        $asset->site()->associate($site);

        $disk = Storage::disk(SiteAsset::DISK);
        $disk->putFileAs(dirname($asset->path), $file, basename($asset->path));

        try {
            DB::transaction(fn () => $asset->save());
        } catch (Throwable $exception) {
            $disk->delete($asset->path);

            throw $exception;
        }

        return back();
    }

    public function show(Site $site, SiteAsset $asset, DesignerScope $scope): StreamedResponse
    {
        $scope->asset($site, $asset);
        Gate::authorize('view', $site);

        return Storage::disk(SiteAsset::DISK)->response(
            $asset->path,
            basename($asset->path),
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ],
        );
    }
}
