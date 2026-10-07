<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One catalog item — a Block (`block`) or a Template (`template`) public ID — and a Site addressed
 * by its Landflow subdomain or public ID.
 */
class GrantSiteLicenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['site' => trim((string) $this->input('site'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'block' => ['required_without:template', 'prohibits:template', 'nullable', 'string', 'ulid'],
            'template' => ['nullable', 'string', 'ulid'],
            'site' => ['required', 'string', 'max:63'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'block.required_without' => 'Выберите блок или шаблон из каталога.',
            'block.prohibits' => 'Выберите один элемент каталога.',
            'block.ulid' => 'Выберите блок из каталога.',
            'template.ulid' => 'Выберите шаблон из каталога.',
            'site.required' => 'Укажите поддомен или ID сайта.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['block' => 'Блок', 'template' => 'Шаблон', 'site' => 'Сайт'];
    }
}
