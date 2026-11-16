<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlatformPermission;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Platform\PlatformAccess;
use App\Support\PlatformAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignWorkspacePlanRequest extends FormRequest
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
            'workspace' => ['required', 'string', 'ulid', 'exists:workspaces,public_id'],
            'plan' => ['required', 'string', Rule::in([Plan::FREE_KEY, Plan::FULL_KEY])],
        ];
    }

    public function owner(): User
    {
        $user = app(PlatformAccess::class)->findByEmail((string) $this->input('email'));

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Пользователь не найден.']);
        }

        return $user;
    }

    public function workspace(): Workspace
    {
        return Workspace::query()->where('public_id', (string) $this->input('workspace'))->firstOrFail();
    }

    public function planKey(): string
    {
        return (string) $this->input('plan');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'email', 'workspace' => 'пространство', 'plan' => 'тариф'];
    }
}
