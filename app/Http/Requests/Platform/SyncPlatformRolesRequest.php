<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\User;
use App\Platform\PlatformAccess;
use App\Support\PlatformAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncPlatformRolesRequest extends FormRequest
{
    public function authorize(PlatformAuthorization $platform): bool
    {
        $user = $this->user();

        return $user instanceof User && $platform->allows($user, PlatformPermission::ManageAccess);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::enum(PlatformRole::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        /** @var list<string> $roles */
        $roles = array_values(array_unique(array_map('strval', $this->input('roles', []))));

        return $roles;
    }

    public function target(): User
    {
        $user = app(PlatformAccess::class)->findByEmail((string) $this->input('email'));

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Пользователь не найден.']);
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'email', 'roles' => 'роли'];
    }
}
