<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Shared untrusted raster image upload rules (D-061): JPEG/PNG/WebP only, no SVG, 10 MB,
 * decoded type must match the detected MIME type.
 */
final class ImageUpload
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_SIDE = 10000;

    /** Detected MIME type => stored extension. */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'mimetypes:'.implode(',', array_keys(self::TYPES))];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'file'): array
    {
        return [
            "{$field}.required" => 'Выберите файл изображения.',
            "{$field}.file" => 'Выберите файл изображения.',
            "{$field}.max" => 'Размер файла не должен превышать 10 МБ.',
            "{$field}.mimetypes" => 'Поддерживаются только изображения JPEG, PNG и WebP.',
        ];
    }

    public static function checkDecoded(Validator $validator, ?UploadedFile $file, string $field = 'file'): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $info = @getimagesize((string) $file?->getRealPath());
        $mime = $file?->getMimeType();

        if ($info === false || $info['mime'] !== $mime) {
            $validator->errors()->add($field, 'Файл повреждён или не является изображением.');
        } elseif ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            $validator->errors()->add($field, 'Каждая сторона изображения должна быть не больше '.self::MAX_SIDE.' px.');
        }
    }
}
