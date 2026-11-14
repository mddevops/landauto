<?php

namespace App\Http\Requests\Blocks;

use App\Support\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Authorization is the Block lookup (own Block only) plus BlockPublisher; this validates the input.
 * The optional preview screenshot becomes the preview of the new version (D-130).
 */
class PublishBlockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'preview' => ['nullable', ...array_slice(ImageUpload::rules(), 1)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->hasFile('preview')) {
                ImageUpload::checkDecoded($validator, $this->file('preview'), 'preview');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ImageUpload::messages('preview');
    }
}
