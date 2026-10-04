<?php

namespace App\Http\Requests\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Field definition. Key and type are chosen once at creation; the key is a stable lowercase
 * machine name unique within the Form. Labels are plain text, never HTML.
 */
class SaveFormFieldRequest extends FormRequest
{
    public const MAX_FIELDS = 30;

    public const MAX_OPTIONS = 30;

    private ?FormField $field = null;

    public function authorize(): bool
    {
        $site = $this->site();
        app(DesignerScope::class)->form($site, $this->form());

        return Gate::allows('editForms', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->existingField() === null;

        return [
            'key' => $creating ? ['required', 'string', 'regex:'.FormField::KEY_PATTERN] : ['prohibited'],
            'type' => $creating ? ['required', Rule::enum(FormFieldType::class)] : ['prohibited'],
            'label' => ['required', 'string', 'max:1000'],
            'placeholder' => ['nullable', 'string', 'max:120'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'required' => ['required', 'boolean'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'options' => ['nullable', 'array', 'max:'.self::MAX_OPTIONS],
            'options.*' => ['required', 'string', 'max:120', 'distinct'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->fieldType();
            $form = $this->form();

            if ($this->existingField() === null) {
                if ($form->fields()->where('key', $this->string('key')->toString())->exists()) {
                    $validator->errors()->add('key', 'Поле с таким ключом уже есть в этой форме.');
                }

                if ($form->fields()->count() >= self::MAX_FIELDS) {
                    $validator->errors()->add('type', 'В форме может быть не более '.self::MAX_FIELDS.' полей.');
                }
            }

            if ($type !== FormFieldType::Consent && mb_strlen($this->string('label')->toString()) > 120) {
                $validator->errors()->add('label', 'Подпись поля не должна превышать 120 символов.');
            }

            if ($type === FormFieldType::Select && count($this->optionList()) === 0) {
                $validator->errors()->add('options', 'Добавьте хотя бы один вариант выбора.');
            }

            $limit = $type->maxLength();

            if ($this->filled('max_length') && ($limit === null || ! in_array($type, [FormFieldType::Text, FormFieldType::Textarea], true) || $this->integer('max_length') > $limit)) {
                $validator->errors()->add('max_length', $limit === null ? 'Ограничение длины не применяется к этому типу поля.' : "Укажите не больше {$limit} символов.");
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => 'Ключ',
            'type' => 'Тип',
            'label' => 'Подпись',
            'placeholder' => 'Подсказка',
            'default_value' => 'Значение',
            'required' => 'Обязательное',
            'max_length' => 'Максимальная длина',
            'options' => 'Варианты',
            'options.*' => 'Вариант',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldAttributes(): array
    {
        $type = $this->fieldType();
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'label' => $this->string('label')->trim()->toString(),
            'placeholder' => $type->isBoolean() || $type === FormFieldType::Hidden ? null : $text('placeholder'),
            'default_value' => $type === FormFieldType::Hidden ? $text('default_value') : null,
            'required' => $type === FormFieldType::Hidden ? false : $this->boolean('required'),
            'validation' => $this->filled('max_length') ? ['max_length' => $this->integer('max_length')] : null,
            'options' => $type === FormFieldType::Select ? $this->optionList() : null,
        ];
    }

    public function fieldType(): FormFieldType
    {
        return $this->existingField()->type ?? FormFieldType::from($this->string('type')->toString());
    }

    public function form(): Form
    {
        $form = $this->route('form');
        abort_unless($form instanceof Form, 404);

        return $form;
    }

    public function existingField(): ?FormField
    {
        $key = $this->route('field');

        if (! is_string($key)) {
            return null;
        }

        return $this->field ??= $this->form()->fields()->where('key', $key)->firstOrFail();
    }

    public function site(): Site
    {
        $site = $this->route('site');
        abort_unless($site instanceof Site, 404);

        return $site;
    }

    /**
     * @return list<string>
     */
    private function optionList(): array
    {
        $options = $this->input('options', []);

        return is_array($options)
            ? array_values(array_filter(array_map(fn (mixed $option): string => is_string($option) ? trim($option) : '', $options), fn (string $option): bool => $option !== ''))
            : [];
    }
}
