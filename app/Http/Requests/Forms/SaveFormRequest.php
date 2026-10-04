<?php

namespace App\Http\Requests\Forms;

use App\Models\Form;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->site();
        $scope = app(DesignerScope::class);
        $form = $this->route('form');
        $form instanceof Form ? $scope->form($site, $form) : $scope->site($site);

        return Gate::allows('editForms', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('form') instanceof Form;

        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => [$updating ? 'required' : 'sometimes', 'boolean'],
            'submit_label' => [$updating ? 'required' : 'sometimes', 'string', 'max:40'],
            'success_message' => [$updating ? 'required' : 'sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Название',
            'status' => 'Статус',
            'submit_label' => 'Текст кнопки',
            'success_message' => 'Сообщение после отправки',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formAttributes(): array
    {
        return array_filter([
            'name' => $this->string('name')->trim()->toString(),
            'status' => $this->has('status') ? $this->boolean('status') : null,
            'submit_label' => $this->has('submit_label') ? $this->string('submit_label')->trim()->toString() : null,
            'success_message' => $this->has('success_message') ? $this->string('success_message')->trim()->toString() : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function site(): Site
    {
        $site = $this->route('site');
        abort_unless($site instanceof Site, 404);

        return $site;
    }
}
