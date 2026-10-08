<?php

namespace App\Http\Requests;

use App\Models\Page;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePageRequest extends FormRequest
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(DesignerScope $scope): bool
    {
        /** @var Site $site */
        $site = $this->route('site');
        $page = $this->route('page');
        $page instanceof Page ? $scope->page($site, $page) : $scope->site($site);

        return Gate::allows($page instanceof Page ? 'editDesign' : 'addPage', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Site $site */
        $site = $this->route('site');
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:'.self::SLUG_PATTERN,
                Rule::unique('pages', 'slug')
                    ->where('site_id', $site->id)
                    ->ignore($page instanceof Page ? $page->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => 'название страницы', 'slug' => 'адрес страницы'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Адрес может содержать только строчные латинские буквы, цифры и дефисы.',
            'slug.unique' => 'Страница с таким адресом уже есть на сайте.',
        ];
    }
}
