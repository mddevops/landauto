<?php

namespace App\Http\Requests;

use App\Enums\SiteType;
use App\Models\Site;
use App\Models\Template;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public const START_BLANK = 'blank';

    public const START_TEMPLATE = 'template';

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
            'site_type' => ['required', 'string', Rule::enum(SiteType::class)],
            'start' => ['required', 'string', Rule::in([self::START_BLANK, self::START_TEMPLATE])],
            'template' => [
                'exclude_unless:start,'.self::START_TEMPLATE,
                'required',
                'string',
                'ulid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Template::query()->availableForSites()->where('public_id', $value)->exists()) {
                        $fail('Выбранный шаблон недоступен.');
                    }
                },
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
            'site_type.required' => 'Выберите формат сайта.',
            'site_type.string' => 'Выбран некорректный формат сайта.',
            'site_type.enum' => 'Выбран некорректный формат сайта.',
            'start.required' => 'Выберите пустой старт или шаблон.',
            'start.string' => 'Выбран некорректный вариант старта.',
            'start.in' => 'Выбран некорректный вариант старта.',
            'template.required' => 'Выберите шаблон.',
            'template.string' => 'Выбран некорректный шаблон.',
            'template.ulid' => 'Выбран некорректный шаблон.',
        ];
    }
}
