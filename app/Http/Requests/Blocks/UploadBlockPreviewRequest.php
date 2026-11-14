<?php

namespace App\Http\Requests\Blocks;

use App\Support\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Preview screenshot of an existing Block Version; authorization is the Block lookup plus BlockPreviews.
 */
class UploadBlockPreviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['preview' => ImageUpload::rules()];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => ImageUpload::checkDecoded($validator, $this->file('preview'), 'preview')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ImageUpload::messages('preview');
    }
}
