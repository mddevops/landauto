<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UploadSiteAssetRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        /** @var Site $site */
        $site = $this->route('site');
        $scope->site($site);

        return Gate::allows('manageAssets', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.SiteAsset::MAX_KILOBYTES,
                'mimetypes:'.implode(',', array_keys(SiteAsset::TYPES)),
            ],
        ];
    }

    /**
     * Decoded image type must agree with the detected MIME type; this rejects polyglots and
     * unreadable files that merely carry an image signature.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $info = @getimagesize((string) $this->file('file')?->getRealPath());
            $mime = $this->file('file')?->getMimeType();

            if ($info === false || $info['mime'] !== $mime) {
                $validator->errors()->add('file', 'Файл повреждён или не является изображением.');
            } elseif ($info[0] < 1 || $info[1] < 1 || $info[0] > SiteAsset::MAX_SIDE || $info[1] > SiteAsset::MAX_SIDE) {
                $validator->errors()->add('file', 'Каждая сторона изображения должна быть не больше '.SiteAsset::MAX_SIDE.' px.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл изображения.',
            'file.file' => 'Выберите файл изображения.',
            'file.max' => 'Размер файла не должен превышать 10 МБ.',
            'file.mimetypes' => 'Поддерживаются только изображения JPEG, PNG и WebP.',
        ];
    }
}
