<?php

namespace App\Http\Controllers\Forms;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\WorkspacePermission;
use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\SiteSecurityPolicy;
use App\Http\Controllers\Controller;
use App\Models\BlacklistEntry;
use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\WorkspaceAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Защита форм»: the Site security policy behind the centralized anti-spam layer
 * (FORMS_AND_INTEGRATIONS.md §12–§13). Editing the policy and Site blacklist requires
 * `edit_site_settings`; the Workspace blacklist requires `edit_workspace`. Blacklisted values
 * are personal data, so each list is sent only to members who may edit it.
 */
class SiteFormSecurityController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private WorkspaceAuthorization $authorization,
    ) {}

    public function show(Request $request, Site $site, CaptchaVerifier $captcha): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        $user = $request->user();
        $canEdit = Gate::allows('update', $site);
        $canEditWorkspace = $user !== null && $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditWorkspace);

        return Inertia::render('sites/form-security', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'policy' => SiteSecurityPolicy::values($site->form_security ?? []),
            'defaults' => SiteSecurityPolicy::values([]),
            'bounds' => SiteSecurityPolicy::BOUNDS,
            'blacklist' => [
                'site' => $canEdit ? $this->entries(BlacklistEntry::query()->where('scope', BlacklistScope::Site->value)->where('site_id', $site->id)) : [],
                'workspace' => $canEditWorkspace ? $this->entries(BlacklistEntry::query()->where('scope', BlacklistScope::Workspace->value)->where('workspace_id', $site->workspace_id)) : [],
            ],
            'choices' => [
                'types' => array_map(fn (BlacklistType $type): array => ['value' => $type->value, 'label' => $type->label()], BlacklistType::cases()),
            ],
            'captcha' => ['configured' => $captcha->isConfigured()],
            'can' => ['edit' => $canEdit, 'editWorkspace' => $canEditWorkspace],
        ]);
    }

    public function update(Request $request, Site $site, CaptchaVerifier $captcha): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('update', $site);

        $rules = [];

        foreach (SiteSecurityPolicy::BOUNDS as $key => [$min, $max]) {
            $rules[$key] = ['required', 'integer', "min:{$min}", "max:{$max}"];
        }

        $rules['captcha_required'] = ['sometimes', 'boolean'];

        $validated = $request->validate($rules, [], [
            'ip_limit' => 'Заявок с одного IP',
            'ip_window_minutes' => 'Период для IP',
            'phone_limit' => 'Заявок с одного телефона',
            'phone_window_minutes' => 'Период для телефона',
            'duplicate_window_minutes' => 'Интервал повторной заявки',
            'captcha_required' => 'Капча',
        ]);

        $settings = [];

        foreach (array_keys(SiteSecurityPolicy::BOUNDS) as $key) {
            $settings[$key] = (int) $validated[$key];
        }

        if (array_key_exists('captcha_required', $validated)) {
            $captchaRequired = (bool) $validated['captcha_required'];

            if ($captchaRequired && ! $captcha->isConfigured()) {
                throw ValidationException::withMessages([
                    'captcha_required' => 'Капча не подключена на платформе. Обратитесь в поддержку.',
                ]);
            }

            $settings['captcha_required'] = $captchaRequired;
        }

        $site->form_security = [
            ...($site->form_security ?? []),
            ...$settings,
        ];
        $site->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки защиты сохранены.']);

        return back();
    }

    /**
     * @param  Builder<BlacklistEntry>  $query
     * @return list<array{public_id: string, type: string, type_label: string, value: string, reason: string|null, expires_at: string|null, active: bool}>
     */
    private function entries(Builder $query): array
    {
        return array_values($query->latest('id')->get()->map(fn (BlacklistEntry $entry): array => [
            'public_id' => $entry->public_id,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'value' => $entry->value,
            'reason' => $entry->reason,
            'expires_at' => $entry->expires_at?->toIso8601String(),
            'active' => $entry->expires_at === null || $entry->expires_at->isFuture(),
        ])->all());
    }
}
