<?php

namespace App\Http\Controllers\Popups;

use App\Enums\PopupAnimation;
use App\Enums\PopupSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Popups\SaveFloatingLauncherRequest;
use App\Http\Requests\Popups\SavePopupRequest;
use App\Models\Form;
use App\Models\Popup;
use App\Models\Site;
use App\Popups\FloatingLauncher;
use App\Popups\PopupBindings;
use App\Popups\PopupRuntime;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Попапы» section: reusable Site-owned modal presentations (D-035).
 */
class SitePopupController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site, PopupRuntime $runtime, PopupBindings $bindings): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        $usage = $bindings->forSite($site);

        return Inertia::render('sites/popups/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'launcher' => FloatingLauncher::present($site),
            'popups' => $site->popups()
                ->with('form.fields')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Popup $popup): array => [
                    ...$runtime->present($popup),
                    'status' => $popup->status,
                    'form_public_id' => $popup->form?->public_id,
                    'bindings' => $usage[$popup->public_id] ?? [],
                ])
                ->values()
                ->all(),
            'forms' => $site->forms()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['public_id', 'name', 'status'])
                ->map(fn (Form $form): array => ['value' => $form->public_id, 'label' => $form->status ? $form->name : "{$form->name} (выключена)"])
                ->values()
                ->all(),
            'choices' => [
                'sizes' => array_map(fn (PopupSize $case): array => ['value' => $case->value, 'label' => $case->label()], PopupSize::cases()),
                'animations' => array_map(fn (PopupAnimation $case): array => ['value' => $case->value, 'label' => $case->label()], PopupAnimation::cases()),
            ],
            'can' => ['editPopups' => Gate::allows('editPopups', $site)],
        ]);
    }

    public function store(SavePopupRequest $request, Site $site): RedirectResponse
    {
        $popup = new Popup($request->popupAttributes());
        $popup->site()->associate($site);
        $popup->form()->associate($request->form());
        $popup->save();

        return back();
    }

    public function update(SavePopupRequest $request, Site $site, Popup $popup): RedirectResponse
    {
        $popup->fill($request->popupAttributes());
        $popup->form()->associate($request->form());
        $popup->save();

        return back();
    }

    public function updateLauncher(SaveFloatingLauncherRequest $request, Site $site): RedirectResponse
    {
        $this->scope->site($site);
        $site->forceFill([
            'floating_launcher_json' => $request->launcher(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Плавающая кнопка сохранена. Изменения появятся на сайте после публикации.']);

        return back();
    }

    public function destroy(Request $request, Site $site, Popup $popup, PopupBindings $bindings): RedirectResponse
    {
        $this->scope->popup($site, $popup);
        Gate::authorize('editPopups', $site);

        $usages = $bindings->forPopup($popup);

        if ($usages !== []) {
            $replace = $request->string('replace_with')->toString();
            $replacement = $replace !== ''
                ? $site->popups()->where('public_id', $replace)->whereKeyNot($popup->id)->first()
                : null;

            if ($replacement === null) {
                return back()->withErrors([
                    'popup' => 'Сначала отвяжите кнопки или укажите другой попап для замены. Попап используется: '.$this->usageSummary($usages),
                ]);
            }

            $bindings->reassign($site, $popup->public_id, $replacement->public_id);
        }

        $popup->delete();

        return back();
    }

    /**
     * @param  list<array{kind: string, label: string, page?: string}>  $usages
     */
    private function usageSummary(array $usages): string
    {
        return implode(', ', array_map(function (array $usage): string {
            $page = $usage['page'] ?? null;

            return $page === null ? $usage['label'] : "{$usage['label']} ({$page})";
        }, $usages));
    }
}
