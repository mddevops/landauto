<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\AutoCatalog;
use App\Http\Controllers\Controller;
use App\Models\SeriesGalleryImage;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Platform gallery of a Series (ADR-010 §5). Customers receive copies, so a photo can be removed
 * at any time without touching customer vehicles or Published Versions.
 */
class SeriesGalleryController extends Controller
{
    public const MAX_FILES = 20;

    public function __construct(private AutoCatalog $catalog) {}

    public function store(Request $request, string $series): RedirectResponse
    {
        $series = $this->catalog->series($series) ?? abort(404);
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => ImageUpload::rules(),
        ], [
            'files.required' => 'Выберите фото.',
            'files.max' => 'За один раз можно загрузить не больше '.self::MAX_FILES.' фото.',
            ...ImageUpload::messages('files.*'),
        ]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files'), fn ($file): bool => $file instanceof UploadedFile));
        $validator = Validator::make([], []);

        foreach ($files as $index => $file) {
            ImageUpload::checkDecoded($validator, $file, "files.{$index}");
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        $order = (int) SeriesGalleryImage::query()->where('catalog_series_ref', $series->ref())->max('sort_order');

        foreach ($files as $index => $file) {
            $info = getimagesize((string) $file->getRealPath()) ?: throw ValidationException::withMessages(["files.{$index}" => 'Файл повреждён или не является изображением.']);
            $mime = (string) $file->getMimeType();
            $image = new SeriesGalleryImage([
                'original_name' => Str::limit(trim($file->getClientOriginalName()) ?: 'image', 250, ''),
                'sort_order' => $order + $index + 1,
            ]);
            $image->public_id = $image->newUniqueId();
            $image->catalog_series_ref = (string) $series->ref();
            $image->path = "series-gallery/{$image->public_id}.".ImageUpload::TYPES[$mime];
            $image->mime_type = $mime;
            $image->size_bytes = (int) $file->getSize();
            $image->width = (int) $info[0];
            $image->height = (int) $info[1];
            $disk = Storage::disk(SeriesGalleryImage::DISK);
            $disk->putFileAs(dirname($image->path), $file, basename($image->path));

            try {
                $image->save();
            } catch (Throwable $exception) {
                $disk->delete($image->path);

                throw $exception;
            }
        }

        return back();
    }

    /**
     * New order of the whole gallery.
     */
    public function reorder(Request $request, string $series): RedirectResponse
    {
        $series = $this->catalog->series($series) ?? abort(404);
        $ids = $request->validate(['images' => ['required', 'array', 'max:500'], 'images.*' => ['string', 'size:26']])['images'];
        $images = SeriesGalleryImage::query()->where('catalog_series_ref', $series->ref())->get()->keyBy('public_id');

        DB::transaction(function () use ($ids, $images): void {
            foreach (array_values($ids) as $position => $id) {
                $images->get($id)?->update(['sort_order' => $position]);
            }
        });

        return back();
    }

    public function destroy(SeriesGalleryImage $image): RedirectResponse
    {
        $path = $image->path;
        $image->delete();
        Storage::disk(SeriesGalleryImage::DISK)->delete($path);

        return back();
    }

    public function show(Request $request, SeriesGalleryImage $image): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return Storage::disk(SeriesGalleryImage::DISK)->response($image->path, basename($image->path), [
            'Content-Type' => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
