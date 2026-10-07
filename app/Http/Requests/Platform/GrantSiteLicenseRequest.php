<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A catalog item (public ID) and a Site addressed by its Landflow subdomain or public ID.
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
            'block' => ['required', 'string', 'ulid'],
            'site' => ['required', 'string', 'max:63'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'block.required' => 'Выберите блок из каталога.',
            'block.ulid' => 'Выберите блок из каталога.',
            'site.required' => 'Укажите поддомен или ID сайта.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['block' => 'Блок', 'site' => 'Сайт'];
    }
}
