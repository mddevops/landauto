<?php

namespace App\Http\Requests\Templates;

use App\Enums\SiteType;
use App\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editable Template settings: name and compatible Site types. Slug and ownership never change.
 */
class UpdateTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Template::NAME_MAX],
            ...StoreTemplateRequest::siteTypeRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreTemplateRequest::siteTypeMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'site_types' => 'Типы сайтов'];
    }

    /**
     * @return list<SiteType>
     */
    public function siteTypes(): array
    {
        /** @var list<string> $values */
        $values = $this->input('site_types', []);

        return array_map(fn (string $value): SiteType => SiteType::from($value), $values);
    }
}
