<?php

namespace App\Http\Controllers;

use App\Automotive\SectionPages;
use App\Enums\SectionPageRole;
use App\Enums\SiteSectionType;
use App\Http\Requests\SaveSiteSectionRequest;
use App\Models\Site;
use App\Models\SiteSection;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\DesignerScope;
use App\Support\SitePaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Разделы» of a multi-page Site: vehicle sections with a customer-chosen address prefix (ADR-011).
 * A changed prefix is kept as a redirect; the public runtime serves sections from the next Publish.
 */
class SiteSectionController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private SectionPages $pages,
    ) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('manageSections', $site);

        $sections = $site->sections()->orderBy('sort_order')->orderBy('id')->get();
        $sections->each(fn (SiteSection $section) => $this->pages->ensure($section));
        $sections->load(['redirects', 'pages']);
        $existing = $sections->map(fn (SiteSection $section): string => $section->type->value)->all();

        return Inertia::render('sites/sections', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'baseUrl' => PublicSiteResolver::primaryUrl($site),
            'sections' => $sections->map(fn (SiteSection $section): array => [
                'public_id' => $section->public_id,
                'type' => $section->type->value,
                'type_label' => $section->type->label(),
                'title' => $section->title,
                'prefix' => $section->prefix,
                'enabled' => $section->enabled,
                'redirects' => $section->redirects->sortByDesc('id')->pluck('prefix')->values()->all(),
                'pages' => collect(SectionPageRole::cases())
                    ->map(fn (SectionPageRole $role): ?array => ($page = $section->pages->firstWhere('section_role', $role)) === null ? null : [
                        'public_id' => $page->public_id,
                        'role' => $role->value,
                        'label' => $role->label(),
                        'path' => implode('/', [$section->prefix, ...$role->exampleSegments()]),
                    ])
                    ->filter()
                    ->values()
                    ->all(),
            ])->values()->all(),
            'types' => array_map(fn (SiteSectionType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'prefix' => $type->defaultPrefix(),
                'available' => $type->isAvailable(),
                'added' => in_array($type->value, $existing, true),
            ], SiteSectionType::cases()),
            'limits' => ['title' => 120, 'prefix' => SitePaths::PREFIX_MAX],
        ]);
    }

    public function store(SaveSiteSectionRequest $request, Site $site): RedirectResponse
    {
        $type = SiteSectionType::from($request->string('type')->toString());
        $prefix = $request->filled('prefix') ? $request->string('prefix')->toString() : $type->defaultPrefix();

        if (! $request->filled('prefix')) {
            $request->checkPrefix($site, $prefix, null, function (string $message): never {
                throw ValidationException::withMessages(['prefix' => "{$message} Укажите адрес раздела вручную."]);
            });
        }

        DB::transaction(function () use ($request, $site, $type, $prefix): void {
            SitePaths::releaseRedirect($site, $prefix);
            $section = new SiteSection([
                'type' => $type,
                'title' => $request->filled('title') ? $request->string('title')->toString() : $type->label(),
                'prefix' => $prefix,
                'enabled' => true,
                'sort_order' => (int) $site->sections()->max('sort_order') + 1,
            ]);
            $section->site()->associate($site);
            $section->save();
            $this->pages->ensure($section);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Раздел добавлен.']);

        return to_route('sites.sections.index', $site);
    }

    public function update(SaveSiteSectionRequest $request, Site $site, SiteSection $section): RedirectResponse
    {
        $prefix = $request->string('prefix')->toString();

        DB::transaction(function () use ($request, $site, $section, $prefix): void {
            $previous = $section->prefix;

            if ($previous !== $prefix) {
                SitePaths::releaseRedirect($site, $prefix);
                $redirect = $section->redirects()->make(['prefix' => $previous]);
                $redirect->site_id = $site->id;
                $redirect->save();
            }

            $section->fill([
                'title' => $request->string('title')->toString(),
                'prefix' => $prefix,
                'enabled' => $request->boolean('enabled'),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Раздел сохранён. Изменения появятся на сайте после публикации.']);

        return to_route('sites.sections.index', $site);
    }

    public function destroy(Site $site, SiteSection $section): RedirectResponse
    {
        $this->scope->section($site, $section);
        Gate::authorize('manageSections', $site);

        DB::transaction(function () use ($section): void {
            foreach ($section->pages()->get() as $page) {
                $page->blocks()->delete();
                $page->delete();
            }

            $section->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Раздел удалён.']);

        return to_route('sites.sections.index', $site);
    }
}
