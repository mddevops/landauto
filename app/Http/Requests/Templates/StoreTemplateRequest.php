<?php

namespace App\Http\Requests\Templates;

use App\Enums\SiteType;
use App\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name, technical slug and compatible Site types of a new Template. Ownership comes from the route
 * (platform or the User's own Developer Profile), never from the request.
 */
class StoreTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => is_string($this->input('slug')) ? trim($this->input('slug')) : $this->input('slug'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Template::NAME_MAX],
            'slug' => ['required', 'string', 'min:3', 'max:60', 'regex:'.Template::SLUG_PATTERN, Rule::unique('templates', 'slug')],
            ...self::siteTypeRules(),
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function siteTypeRules(): array
    {
        return [
            'site_types' => ['required', 'array', 'min:1'],
            'site_types.*' => ['required', Rule::enum(SiteType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и одиночные дефисы.',
            'slug.unique' => 'Этот slug уже используется другим шаблоном.',
            ...self::siteTypeMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function siteTypeMessages(): array
    {
        return [
            'site_types.required' => 'Выберите хотя бы один тип сайта.',
            'site_types.min' => 'Выберите хотя бы один тип сайта.',
            'site_types.*.enum' => 'Выберите тип сайта из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'slug' => 'Slug', 'site_types' => 'Типы сайтов'];
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
