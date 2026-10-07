<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePageRequest;
use App\Models\Page;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SitePageController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function store(SavePageRequest $request, Site $site): RedirectResponse
    {
        $title = $request->string('title')->toString();
        $page = new Page([
            'title' => $title,
            'slug' => $request->filled('slug') ? $request->string('slug')->toString() : $this->uniqueSlug($site, $title),
            'sort_order' => (int) $site->pages()->max('sort_order') + 1,
        ]);
        $page->site()->associate($site);
        $page->save();

        return to_route('sites.designer', ['site' => $site, 'page' => $page->public_id]);
    }

    public function update(SavePageRequest $request, Site $site, Page $page): RedirectResponse
    {
        $page->title = $request->string('title')->toString();

        if (! $page->is_home && $request->filled('slug')) {
            $page->slug = $request->string('slug')->toString();
        }

        $page->save();

        return to_route('sites.designer', ['site' => $site, 'page' => $page->public_id]);
    }

    public function destroy(Site $site, Page $page): RedirectResponse
    {
        $this->scope->page($site, $page);
        Gate::authorize('addPage', $site);

        if ($page->is_home) {
            throw ValidationException::withMessages(['page' => 'Главную страницу удалить нельзя.']);
        }

        DB::transaction(function () use ($page): void {
            $page->blocks()->delete();
            $page->delete();
        });

        return to_route('sites.designer', ['site' => $site]);
    }

    private function uniqueSlug(Site $site, string $title): string
    {
        $base = Str::limit(Str::slug($title), 90, '') ?: 'page';
        $slug = $base;

        for ($suffix = 2; $site->pages()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
