<?php

namespace App\Http\Controllers;

use App\Models\BlockVersionPreview;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Block Version previews of catalog Blocks (platform and Developer) are visible to any signed-in
 * user, like the catalog itself; Workspace-private Blocks have none.
 */
class BlockPreviewController extends Controller
{
    public function show(string $preview): StreamedResponse
    {
        $model = BlockVersionPreview::query()
            ->where('public_id', $preview)
            ->whereHas('version.definition', fn ($query) => $query->inCatalog())
            ->firstOrFail();

        abort_unless(Storage::disk(BlockVersionPreview::DISK)->exists($model->path), 404);

        return Storage::disk(BlockVersionPreview::DISK)->response(
            $model->path,
            basename($model->path),
            [
                'Content-Type' => $model->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ],
        );
    }
}
