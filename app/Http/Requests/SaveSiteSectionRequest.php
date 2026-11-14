<?php

namespace App\Http\Requests;

use App\Enums\SiteSectionType;
use App\Models\Site;
use App\Models\SiteSection;
use App\Support\DesignerScope;
use App\Support\SitePaths;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Creates a section (type, optional title / prefix) or changes its title, prefix and visibility.
 */
class SaveSiteSectionRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        /** @var Site $site */
        $site = $this->route('site');
        $section = $this->route('section');
        $section instanceof SiteSection ? $scope->section($site, $section) : $scope->site($site);

        return Gate::allows('manageSections', $site);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('prefix'))) {
            $this->merge(['prefix' => mb_strtolower(trim($this->input('prefix')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Site $site */
        $site = $this->route('site');
        $section = $this->route('section');
        $creating = ! $section instanceof SiteSection;

        return [
            'type' => $creating ? [
                'required',
                Rule::enum(SiteSectionType::class)->only(array_filter(
                    SiteSectionType::cases(),
                    fn (SiteSectionType $type): bool => $type->isAvailable(),
                )),
                Rule::unique('site_sections', 'type')->where('site_id', $site->id),
            ] : ['prohibited'],
            'title' => [$creating ? 'nullable' : 'required', 'string', 'max:120'],
            'prefix' => [
                $creating ? 'nullable' : 'required',
                'string',
                'max:'.SitePaths::PREFIX_MAX,
                'regex:'.SitePaths::PATTERN,
                function (string $attribute, mixed $value, Closure $fail) use ($site, $section): void {
                    $this->checkPrefix($site, (string) $value, $section instanceof SiteSection ? $section : null, $fail);
                },
            ],
            'enabled' => [$creating ? 'nullable' : 'required', 'boolean'],
        ];
    }

    public function checkPrefix(Site $site, string $prefix, ?SiteSection $section, Closure $fail): void
    {
        if (SitePaths::isReserved($prefix)) {
            $fail('Этот адрес зарезервирован, выберите другой.');
        } elseif (SitePaths::usedByPage($site, $prefix)) {
            $fail('Такой адрес уже занят страницей сайта.');
        } elseif (SitePaths::usedBySection($site, $prefix, $section?->id)) {
            $fail('Такой адрес уже занят другим разделом.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'раздел', 'title' => 'название раздела', 'prefix' => 'адрес раздела', 'enabled' => 'показ раздела'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.unique' => 'Такой раздел уже есть на сайте.',
            'type.enum' => 'Этот раздел пока недоступен.',
            'prefix.regex' => 'Адрес может содержать только строчные латинские буквы, цифры и дефисы.',
        ];
    }
}
