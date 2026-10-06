<?php

namespace App\Http\Requests\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMemberSiteAccessRequest extends FormRequest
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
            'site_access_mode' => ['required', 'string', Rule::enum(SiteAccessMode::class)],
            'sites' => ['array', 'max:500'],
            'sites.*' => ['string', 'ulid'],
        ];
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
        return ['site_access_mode' => 'доступ к сайтам', 'sites' => 'сайты', 'sites.*' => 'сайт'];
    }
}
