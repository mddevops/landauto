<?php

namespace App\Http\Requests\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWorkspaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(WorkspacePermission::ManageMembers->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::enum(WorkspaceRole::class)],
            'site_access_mode' => ['nullable', 'string', Rule::enum(SiteAccessMode::class)],
            'sites' => ['array', 'max:500'],
            'sites.*' => ['string', 'ulid'],
        ];
    }

    public function siteAccessMode(): SiteAccessMode
    {
        return SiteAccessMode::tryFrom($this->string('site_access_mode')->toString()) ?? SiteAccessMode::AllSites;
    }

    /**
     * @return list<string>
     */
    public function sitePublicIds(): array
    {
        return array_values(array_map('strval', (array) $this->input('sites', [])));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'роль', 'site_access_mode' => 'доступ к сайтам', 'sites' => 'сайты', 'sites.*' => 'сайт'];
    }
}
