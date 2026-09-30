<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => $this->normalizeEmail($this->input('email'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        // Changing the sign-in email requires re-authentication to prevent account takeover.
        if ($this->changesEmail()) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        return $rules;
    }

    /**
     * Determine whether the submitted email differs from the user's current email.
     */
    public function changesEmail(): bool
    {
        return $this->input('email') !== $this->user()->email;
    }
}
