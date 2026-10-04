<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSiteDesignRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        /** @var Site $site */
        $site = $this->route('site');
        $scope->site($site);

        return Gate::allows('editDesign', $site);
    }

    protected function prepareForValidation(): void
    {
        foreach (['primary_color', 'secondary_color'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => strtolower($this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return SiteDesignTokens::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'primary_color' => 'основной цвет',
            'secondary_color' => 'дополнительный цвет',
            'font_family' => 'шрифт',
            'radius' => 'скругление',
            'container' => 'ширина контента',
            'button_style' => 'стиль кнопок',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Укажите цвет в формате #RRGGBB.',
            'secondary_color.regex' => 'Укажите цвет в формате #RRGGBB.',
        ];
    }
}
