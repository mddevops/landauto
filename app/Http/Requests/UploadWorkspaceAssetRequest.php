<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use App\Support\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UploadWorkspaceAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(WorkspacePermission::ManageWorkspaceAssets->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ImageUpload::rules()];
    }

    /**
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
