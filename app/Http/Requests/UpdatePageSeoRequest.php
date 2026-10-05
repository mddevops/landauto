<?php

namespace App\Http\Requests;

use App\Models\Page;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePageSeoRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        $scope->page($this->site(), $this->page());

        return Gate::allows('editSeo', $this->site());
    }

    public function site(): Site
    {
        /** @var Site $site */
        $site = $this->route('site');

        return $site;
    }

    public function page(): Page
    {
        /** @var Page $page */
        $page = $this->route('page');

        return $page;
    }

    protected function prepareForValidation(): void
    {
        foreach (['seo_title', 'seo_description'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key)) === '' ? null : trim($this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seo_title.string' => 'Заголовок для поисковиков должен быть строкой.',
            'seo_title.max' => 'Заголовок для поисковиков не должен превышать 120 символов.',
            'seo_description.string' => 'Описание должно быть строкой.',
            'seo_description.max' => 'Описание не должно превышать 300 символов.',
            'seo_noindex.boolean' => 'Некорректное значение индексации.',
        ];
    }
}
