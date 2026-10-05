<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationStatus;
use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Интеграции сайта»: bindings of Workspace profiles to this Site with Site-specific, non-secret
 * overrides (D-040). The profile is resolved on the server inside the Site's own Workspace.
 */
class SiteIntegrationController extends Controller
{
    public const MAX_OVERRIDES = 20;

    public const OVERRIDE_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewIntegrations', $site);

        $bindings = $site->integrationBindings()
            ->with('profile')
            ->where('status', '!=', IntegrationStatus::Archived->value)
            ->orderBy('id')
            ->get();

        return Inertia::render('sites/integrations', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'bindings' => array_values($bindings->map(fn (SiteIntegrationBinding $binding): array => self::present($binding))->all()),
            'profiles' => array_values(IntegrationProfile::query()
                ->where('workspace_id', $site->workspace_id)
                ->where('status', IntegrationStatus::Active->value)
                ->orderBy('name')
                ->get()
                ->map(fn (IntegrationProfile $profile): array => ['value' => $profile->public_id, 'label' => $profile->name.' — '.$profile->provider_type->label()])
                ->all()),
            'can' => ['manage' => Gate::allows('manageIntegrations', $site)],
        ]);
    }

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('manageIntegrations', $site);

        $validated = $request->validate([
            'profile' => ['required', 'string'],
            ...$this->rules(),
        ], [], $this->attributes());

        $profile = IntegrationProfile::query()
            ->where('workspace_id', $site->workspace_id)
            ->where('public_id', strtolower($validated['profile']))
            ->where('status', IntegrationStatus::Active->value)
            ->first();

        if ($profile === null) {
            throw ValidationException::withMessages(['profile' => 'Выберите активное подключение этого рабочего пространства.']);
        }

        $binding = new SiteIntegrationBinding([
            'name' => $validated['name'] ?? null,
            'overrides_json' => self::overrides($validated['overrides'] ?? []),
        ]);
        $binding->site_id = $site->id;
        $binding->integration_profile_id = $profile->id;
        $binding->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Подключение добавлено к сайту.']);

        return back();
    }

    public function update(Request $request, Site $site, string $binding): RedirectResponse
    {
        $model = $this->find($site, $binding);
        Gate::authorize('manageIntegrations', $site);

        $validated = $request->validate([
            ...$this->rules(),
            'status' => ['required', Rule::in([IntegrationStatus::Active->value, IntegrationStatus::Disabled->value])],
        ], [], $this->attributes());

        $model->fill([
            'name' => $validated['name'] ?? null,
            'overrides_json' => self::overrides($validated['overrides'] ?? []),
        ]);
        $model->status = IntegrationStatus::from($validated['status']);
        $model->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки подключения сохранены.']);

        return back();
    }

    /**
     * A binding used by Form routes is archived instead of deleted so delivery history keeps it.
     */
    public function destroy(Site $site, string $binding): RedirectResponse
    {
        $model = $this->find($site, $binding);
        Gate::authorize('manageIntegrations', $site);

        if (self::isReferenced($model)) {
            $model->status = IntegrationStatus::Archived;
            $model->save();
        } else {
            $model->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Подключение отвязано от сайта.']);

        return back();
    }

    public static function isReferenced(SiteIntegrationBinding $binding): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(SiteIntegrationBinding $binding): array
    {
        return [
            'public_id' => $binding->public_id,
            'name' => $binding->name,
            'status' => $binding->status->value,
            'status_label' => $binding->status->label(),
            'overrides' => array_map(
                fn (string $key, string $value): array => ['key' => $key, 'value' => $value],
                array_keys($binding->overrides_json),
                array_values($binding->overrides_json),
            ),
            'profile' => [
                'public_id' => $binding->profile->public_id,
                'name' => $binding->profile->name,
                'provider_type_label' => $binding->profile->provider_type->label(),
                'status' => $binding->profile->status->value,
                'status_label' => $binding->profile->status->label(),
            ],
        ];
    }

    private function find(Site $site, string $publicId): SiteIntegrationBinding
    {
        $this->scope->site($site);

        return $site->integrationBindings()
            ->with('profile')
            ->where('public_id', strtolower($publicId))
            ->where('status', '!=', IntegrationStatus::Archived->value)
            ->firstOrFail();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'overrides' => ['nullable', 'array', 'max:'.self::MAX_OVERRIDES],
            'overrides.*.key' => ['required', 'string', 'regex:'.self::OVERRIDE_KEY_PATTERN, 'distinct'],
            'overrides.*.value' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'profile' => 'Подключение',
            'name' => 'Название',
            'status' => 'Статус',
            'overrides' => 'Параметры сайта',
            'overrides.*.key' => 'Ключ параметра',
            'overrides.*.value' => 'Значение параметра',
        ];
    }

    /**
     * @param  list<array{key: string, value: string}>  $rows
     * @return array<string, string>
     */
    private static function overrides(array $rows): array
    {
        $overrides = [];

        foreach ($rows as $row) {
            $overrides[$row['key']] = $row['value'];
        }

        return $overrides;
    }
}
