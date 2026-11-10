<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlatformPermission;
use App\Platform\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(PlatformPermission::ManagePlatformSettings->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            PlatformSettings::SUBMISSION_RETENTION_MONTHS => ['required', 'integer', 'min:1', 'max:120'],
            PlatformSettings::ACCOUNT_DELETION_GRACE_DAYS => ['required', 'integer', 'min:1', 'max:365'],
            PlatformSettings::FREE_MAX_STORAGE_MB => ['required', 'integer', 'min:1', 'max:1048576'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            PlatformSettings::SUBMISSION_RETENTION_MONTHS => 'срок хранения заявок',
            PlatformSettings::ACCOUNT_DELETION_GRACE_DAYS => 'отсрочка удаления аккаунта',
            PlatformSettings::FREE_MAX_STORAGE_MB => 'лимит хранилища Free',
        ];
    }
}
