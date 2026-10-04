<?php

namespace App\Http\Requests\Platform;

use App\Enums\MediaAngle;
use App\Enums\PlatformPermission;
use App\Models\SeriesMediaSet;
use App\Support\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadSeriesMediaImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(PlatformPermission::ManageCatalogMedia->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ImageUpload::rules(),
            'angle' => ['required', Rule::enum(MediaAngle::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            ImageUpload::checkDecoded($validator, $this->file('file'));

            /** @var SeriesMediaSet $set */
            $set = $this->route('set');

            if ($validator->errors()->isEmpty() && $set->images()->where('angle', $this->string('angle')->toString())->exists()) {
                $validator->errors()->add('angle', 'Для этого ракурса уже есть изображение. Удалите его, чтобы загрузить новое.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ImageUpload::messages(),
            'angle.required' => 'Выберите ракурс.',
            'angle.enum' => 'Выберите ракурс из списка.',
        ];
    }
}
