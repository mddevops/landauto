<?php

namespace App\Http\Controllers\Forms;

use App\Forms\SiteSecurityPolicy;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Защита форм»: the Site security policy behind the centralized anti-spam layer
 * (FORMS_AND_INTEGRATIONS.md §12). Editing requires `edit_site_settings`.
 */
class SiteFormSecurityController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function show(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        return Inertia::render('sites/form-security', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'policy' => SiteSecurityPolicy::values($site->form_security ?? []),
            'defaults' => SiteSecurityPolicy::values([]),
            'bounds' => SiteSecurityPolicy::BOUNDS,
            'can' => ['edit' => Gate::allows('update', $site)],
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('update', $site);

        $rules = [];

        foreach (SiteSecurityPolicy::BOUNDS as $key => [$min, $max]) {
            $rules[$key] = ['required', 'integer', "min:{$min}", "max:{$max}"];
        }

        $validated = $request->validate($rules, [], [
            'ip_limit' => 'Заявок с одного IP',
            'ip_window_minutes' => 'Период для IP',
            'phone_limit' => 'Заявок с одного телефона',
            'phone_window_minutes' => 'Период для телефона',
            'duplicate_window_minutes' => 'Интервал повторной заявки',
        ]);

        $site->form_security = [
            ...($site->form_security ?? []),
            ...array_map(intval(...), $validated),
        ];
        $site->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки защиты сохранены.']);

        return back();
    }
}
