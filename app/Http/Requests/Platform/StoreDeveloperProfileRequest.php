<?php

namespace App\Http\Requests\Platform;

use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The email only locates an existing verified account for the Super Admin; it is never an
 * ownership or authorization key for the profile.
 */
class StoreDeveloperProfileRequest extends FormRequest
{
    private ?User $owner = null;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'display_name' => trim((string) $this->input('display_name')),
            'slug' => trim((string) $this->input('slug')),
            'bio' => trim((string) $this->input('bio')) === '' ? null : trim((string) $this->input('bio')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'display_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'min:'.DeveloperProfile::SLUG_MIN,
                'max:'.DeveloperProfile::SLUG_MAX,
                'regex:'.DeveloperProfile::SLUG_PATTERN,
                Rule::unique('developer_profiles', 'slug'),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и одиночные дефисы между ними.',
            'slug.unique' => 'Этот slug уже занят другим разработчиком.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'Email пользователя',
            'display_name' => 'Название разработчика',
            'slug' => 'Slug',
            'bio' => 'Описание',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $owner = User::query()->where('email', $this->string('email')->toString())->first();

                if ($owner === null) {
                    $validator->errors()->add('email', 'Пользователь с таким email не найден. Профиль выдаётся только существующей учётной записи.');
                } elseif ($owner->email_verified_at === null) {
                    $validator->errors()->add('email', 'Пользователь ещё не подтвердил email.');
                } elseif ($owner->developerProfile()->exists()) {
                    $validator->errors()->add('email', 'У этого пользователя уже есть профиль разработчика.');
                } else {
                    $this->owner = $owner;
                }
            },
        ];
    }

    public function owner(): User
    {
        return $this->owner ?? throw new \LogicException('The owner is resolved during validation.');
    }
}
