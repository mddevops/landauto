<?php

namespace App\Http\Controllers\Popups;

use App\Enums\PopupAnimation;
use App\Enums\PopupSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Popups\SavePopupRequest;
use App\Models\Popup;
use App\Models\Site;
use App\Popups\PopupRuntime;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Попапы» section: reusable Site-owned modal presentations (D-035).
 */
class SitePopupController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site, PopupRuntime $runtime): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        return Inertia::render('sites/popups/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'popups' => $site->popups()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Popup $popup): array => [...$runtime->present($popup), 'status' => $popup->status])
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
        $popup->site()->associate($site)->save();

        return back();
    }

    public function update(SavePopupRequest $request, Site $site, Popup $popup): RedirectResponse
    {
        $popup->update($request->popupAttributes());

        return back();
    }

    public function destroy(Site $site, Popup $popup): RedirectResponse
    {
        $this->scope->popup($site, $popup);
        Gate::authorize('editPopups', $site);

        $popup->delete();

        return back();
    }
}
