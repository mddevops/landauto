<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\ImageUpload;
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
        return ['file' => ImageUpload::rules()];
    }

    /**
     * Decoded image type must agree with the detected MIME type; this rejects polyglots and
     * unreadable files that merely carry an image signature.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => ImageUpload::checkDecoded($validator, $this->file('file'))];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ImageUpload::messages();
    }
}
