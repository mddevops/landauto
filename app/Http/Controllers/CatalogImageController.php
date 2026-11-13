<?php

namespace App\Http\Controllers;

use App\Catalog\AutoCatalog;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Images of the auto.ru catalog (mark logos, series photos) for signed-in users. The route
 * restricts the path to the known catalog folders; anything else is 404.
 */
class CatalogImageController extends Controller
{
    public const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    public function show(string $path): StreamedResponse
    {
        abort_unless(preg_match(AutoCatalog::IMAGE_PATH_PATTERN, $path) === 1, 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME_TYPES[$extension] ?? abort(404);
        $disk = Storage::disk(AutoCatalog::IMAGES_DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=604800',
            ...($mime === 'image/svg+xml' ? ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"] : []),
        ]);
    }
}
