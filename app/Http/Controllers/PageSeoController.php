<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePageSeoRequest;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Draft SEO of a Page (title, description, indexing). Visitors see it after the next Publish.
 */
class PageSeoController extends Controller
{
    public function update(UpdatePageSeoRequest $request, Site $site, Page $page): RedirectResponse
    {
        $page->seo_title = $request->validated('seo_title');
        $page->seo_description = $request->validated('seo_description');

        if ($request->has('seo_noindex') && Gate::allows('editSeoIndexing', $site)) {
            $page->seo_noindex = $request->boolean('seo_noindex');
        }

        $page->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'SEO страницы сохранено.']);

        return $request->validated('return') === 'seo'
            ? to_route('sites.seo.index', $site)
            : to_route('sites.designer', ['site' => $site, 'page' => $page->public_id]);
    }
}
