<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteAnalyticsSettings;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Draft Yandex Metrica settings (D-044). Saving never touches the live site: the counter reaches
 * visitors only after the next Publish.
 */
class SiteAnalyticsController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('manageIntegrations', $site);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'counter_id' => ['nullable', 'required_if_accepted:enabled', 'string', 'regex:'.SiteAnalyticsSettings::COUNTER_PATTERN],
            'clickmap' => ['required', 'boolean'],
            'track_links' => ['required', 'boolean'],
            'accurate_track_bounce' => ['required', 'boolean'],
            'webvisor' => ['required', 'boolean'],
        ], [
            'counter_id.regex' => 'Номер счётчика — только цифры, от 4 до 16 знаков.',
        ], [
            'counter_id' => 'номер счётчика',
        ]);

        $settings = $site->analyticsSettings()->firstOrNew();
        $settings->fill([
            'yandex_metrica_enabled' => (bool) $validated['enabled'],
            'yandex_metrica_counter_id' => $validated['counter_id'] ?? null,
            'clickmap' => (bool) $validated['clickmap'],
            'track_links' => (bool) $validated['track_links'],
            'accurate_track_bounce' => (bool) $validated['accurate_track_bounce'],
            'webvisor_enabled' => (bool) $validated['webvisor'],
        ]);
        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки Яндекс Метрики сохранены. Они появятся на сайте после публикации.']);

        return back();
    }

    /**
     * @return array{enabled: bool, counter_id: string|null, clickmap: bool, track_links: bool, accurate_track_bounce: bool, webvisor: bool}
     */
    public static function present(?SiteAnalyticsSettings $settings): array
    {
        $settings ??= new SiteAnalyticsSettings;

        return [
            'enabled' => $settings->yandex_metrica_enabled,
            'counter_id' => $settings->yandex_metrica_counter_id,
            'clickmap' => $settings->clickmap,
            'track_links' => $settings->track_links,
            'accurate_track_bounce' => $settings->accurate_track_bounce,
            'webvisor' => $settings->webvisor_enabled,
        ];
    }
}
