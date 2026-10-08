<?php

namespace App\Http\Requests\Platform;

use App\Enums\CatalogLicenseScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One catalog item — a Block (`block`) or a Template (`template`) public ID — and one target
 * (D-121): a Site addressed by its Landflow subdomain or public ID, or a Workspace addressed by its
 * public ID or by the subdomain / public ID of any of its Sites.
 */
class GrantCatalogLicenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['target' => trim((string) $this->input('target'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'block' => ['required_without:template', 'prohibits:template', 'nullable', 'string', 'ulid'],
            'template' => ['nullable', 'string', 'ulid'],
            'scope' => ['required', Rule::enum(CatalogLicenseScope::class)],
            'target' => ['required', 'string', 'max:63'],
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
            'scope.required' => 'Выберите, на что выдаётся лицензия.',
            'scope.enum' => 'Выберите: один сайт или всё пространство.',
            'target.required' => 'Укажите сайт или пространство.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['block' => 'Блок', 'template' => 'Шаблон', 'scope' => 'Область', 'target' => 'Получатель'];
    }

    public function scope(): CatalogLicenseScope
    {
        return CatalogLicenseScope::from($this->string('scope')->toString());
    }
}
