<?php

namespace App\Http\Requests\Popups;

use App\Enums\PopupAnimation;
use App\Enums\PopupSize;
use App\Models\Popup;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->site();
        $scope = app(DesignerScope::class);
        $popup = $this->route('popup');
        $popup instanceof Popup ? $scope->popup($site, $popup) : $scope->site($site);

        return Gate::allows('editPopups', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:160'],
            'text' => ['nullable', 'string', 'max:2000'],
            'size' => ['required', Rule::enum(PopupSize::class)],
            'animation' => ['required', Rule::enum(PopupAnimation::class)],
            'close_on_overlay' => ['required', 'boolean'],
            'close_on_escape' => ['required', 'boolean'],
            'show_close_button' => ['required', 'boolean'],
            'mobile_fullscreen' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! $this->boolean('show_close_button') && ! $this->boolean('close_on_escape')) {
                $validator->errors()->add('show_close_button', 'Оставьте кнопку закрытия или закрытие клавишей Escape, иначе посетитель не сможет закрыть окно.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Название',
            'status' => 'Статус',
            'title' => 'Заголовок',
            'text' => 'Текст',
            'size' => 'Размер',
            'animation' => 'Анимация',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function popupAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'status' => $this->boolean('status'),
            'title' => $this->filled('title') ? $this->string('title')->trim()->toString() : null,
            'text' => $this->filled('text') ? $this->string('text')->trim()->toString() : null,
            'size' => $this->string('size')->toString(),
            'animation' => $this->string('animation')->toString(),
            'close_on_overlay' => $this->boolean('close_on_overlay'),
            'close_on_escape' => $this->boolean('close_on_escape'),
            'show_close_button' => $this->boolean('show_close_button'),
            'mobile_fullscreen' => $this->boolean('mobile_fullscreen'),
        ];
    }

    public function site(): Site
    {
        $site = $this->route('site');
        abort_unless($site instanceof Site, 404);

        return $site;
    }
}
