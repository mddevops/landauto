<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProviderType;
use App\Enums\IntegrationStatus;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Integrations\Http\SafeHeaders;
use App\Integrations\IntegrationProfiles;
use App\Models\IntegrationProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Интеграции» of the current Workspace (D-039). Profiles are always resolved inside the
 * current Workspace; credentials are written encrypted and never sent back to the browser.
 */
class IntegrationProfileController extends Controller
{
    public const ATTRIBUTES = [
        'name' => 'Название',
        'provider_type' => 'Тип подключения',
        'provider_key' => 'Код системы',
        'base_url' => 'Адрес',
        'auth_type' => 'Авторизация',
        'api_key_header' => 'Заголовок для ключа',
        'status' => 'Статус',
        'credential_token' => 'Токен или ключ',
        'credential_username' => 'Логин',
        'credential_password' => 'Пароль',
    ];

    public function __construct(private IntegrationProfiles $profiles) {}

    public function index(Request $request): Response
    {
        abort_unless(Gate::allows(WorkspacePermission::ViewIntegrations->value) || Gate::allows(WorkspacePermission::ManageIntegrations->value), 403);

        $profiles = IntegrationProfile::query()
            ->where('workspace_id', $this->profiles->workspace()->id)
            ->where('status', '!=', IntegrationStatus::Archived->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('integrations/index', [
            'profiles' => array_values($profiles->map(fn (IntegrationProfile $profile): array => $this->profiles->present($profile))->all()),
            'choices' => [
                'providers' => array_map(fn (IntegrationProviderType $type): array => ['value' => $type->value, 'label' => $type->label()], IntegrationProviderType::cases()),
                'authTypes' => array_map(fn (IntegrationAuthType $type): array => ['value' => $type->value, 'label' => $type->label()], IntegrationAuthType::cases()),
            ],
            'can' => ['manage' => Gate::allows(WorkspacePermission::ManageIntegrations->value)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageIntegrations->value);

        $validated = $request->validate([
            'provider_type' => ['required', Rule::enum(IntegrationProviderType::class)],
            ...$this->rules(),
            ...$this->profiles->credentialRules((string) $request->input('auth_type'), required: true),
        ], [], self::ATTRIBUTES);

        $profile = new IntegrationProfile;
        $profile->workspace_id = $this->profiles->workspace()->id;
        $profile->provider_type = IntegrationProviderType::from($validated['provider_type']);
        $this->fill($profile, $validated);
        $this->profiles->writeCredentials($profile, $validated);
        $profile->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Подключение создано.']);

        return back();
    }

    public function update(Request $request, string $profile): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageIntegrations->value);
        $model = $this->profiles->find($profile);

        $validated = $request->validate([
            ...$this->rules(),
            'status' => ['required', Rule::in([IntegrationStatus::Active->value, IntegrationStatus::Disabled->value])],
        ], [], self::ATTRIBUTES);

        $authChanged = $model->auth_type->value !== $validated['auth_type'];

        if ($authChanged) {
            $request->validate($this->profiles->credentialRules($validated['auth_type'], required: true), [], self::ATTRIBUTES);
        }

        $this->fill($model, $validated);
        $model->status = IntegrationStatus::from($validated['status']);

        if ($authChanged) {
            $this->profiles->writeCredentials($model, $request->only(array_keys($this->profiles->credentialRules($validated['auth_type'], required: true))));
        }

        $model->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Подключение сохранено.']);

        return back();
    }

    /**
     * Unused profiles are deleted; profiles still referenced by Site bindings or Form routes are
     * archived so delivery history keeps its context.
     */
    public function destroy(string $profile): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageIntegrations->value);
        $model = $this->profiles->find($profile);

        if ($this->profiles->isReferenced($model)) {
            $model->status = IntegrationStatus::Archived;
            $model->save();
            $message = 'Подключение используется, поэтому перенесено в архив.';
        } else {
            $model->delete();
            $message = 'Подключение удалено.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'provider_key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_.-]*$/'],
            'base_url' => ['required', 'string', 'max:2048', 'url:https,http'],
            'auth_type' => ['required', Rule::enum(IntegrationAuthType::class)],
            'api_key_header' => [
                Rule::requiredIf(fn (): bool => request()->input('auth_type') === IntegrationAuthType::ApiKeyHeader->value),
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! SafeHeaders::isAllowedName($value)) {
                        $fail('Укажите допустимое имя заголовка, например X-Api-Key.');
                    }
                },
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fill(IntegrationProfile $profile, array $validated): void
    {
        $authType = IntegrationAuthType::from($validated['auth_type']);
        $settings = $profile->settings_json ?? [];
        unset($settings['api_key_header']);

        if ($authType === IntegrationAuthType::ApiKeyHeader) {
            $settings['api_key_header'] = $validated['api_key_header'];
        }

        $profile->fill([
            'name' => $validated['name'],
            'provider_key' => $validated['provider_key'] ?? null,
            'base_url' => $validated['base_url'],
            'settings_json' => $settings === [] ? null : $settings,
        ]);
        $profile->auth_type = $authType;
    }
}
