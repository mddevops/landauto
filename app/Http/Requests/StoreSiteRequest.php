<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Site::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template' => [
                'required',
                'string',
                'ulid',
                Rule::exists('templates', 'public_id')->where('is_official', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название сайта.',
            'name.string' => 'Название сайта должно быть строкой.',
            'name.max' => 'Название сайта не должно превышать 255 символов.',
            'template.required' => 'Выберите шаблон.',
            'template.string' => 'Выбран некорректный шаблон.',
            'template.ulid' => 'Выбран некорректный шаблон.',
            'template.exists' => 'Выбранный шаблон недоступен.',
        ];
    }
}
