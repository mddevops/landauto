<?php

namespace App\Http\Controllers\Platform;

use App\Enums\MediaAngle;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UploadSeriesMediaImageRequest;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SeriesMediaImageController extends Controller
{
    public function store(UploadSeriesMediaImageRequest $request, SeriesMediaSet $set): RedirectResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);
        $mime = (string) $file->getMimeType();
        $info = getimagesize((string) $file->getRealPath());
        abort_if($info === false, 422);

        $image = new SeriesMediaImage(['original_name' => Str::limit(trim($file->getClientOriginalName()) ?: 'image', 250, '')]);
        $image->public_id = $image->newUniqueId();
        $image->angle = MediaAngle::from($request->string('angle')->toString());
        $image->path = "series-media/{$set->public_id}/{$image->public_id}.".ImageUpload::TYPES[$mime];
        $image->mime_type = $mime;
        $image->size_bytes = (int) $file->getSize();
        $image->width = (int) $info[0];
        $image->height = (int) $info[1];
        $image->set()->associate($set);

        $disk = Storage::disk(SeriesMediaImage::DISK);
        $disk->putFileAs(dirname($image->path), $file, basename($image->path));

        try {
            DB::transaction(fn () => $image->save());
        } catch (Throwable $exception) {
            $disk->delete($image->path);

            throw $exception;
        }

        return back();
    }

    public function destroy(SeriesMediaImage $image): RedirectResponse
    {
        Gate::authorize(PlatformPermission::ManageCatalogMedia->value);

        $path = $image->path;
        DB::transaction(fn () => $image->delete());
        Storage::disk(SeriesMediaImage::DISK)->delete($path);

        return back();
    }

    /**
     * Prepared platform media is readable by any signed-in user while its set is active;
     * inactive sets stay visible to catalog staff only.
     */
    public function show(Request $request, SeriesMediaImage $image): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $image->set->status || ($user instanceof User && Gate::forUser($user)->allows(PlatformPermission::ViewCatalog->value)),
            404,
        );

        return Storage::disk(SeriesMediaImage::DISK)->response(
            $image->path,
            basename($image->path),
            [
                'Content-Type' => $image->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ],
        );
    }
}
