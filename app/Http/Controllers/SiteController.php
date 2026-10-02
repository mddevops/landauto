<?php

namespace App\Http\Controllers;

use App\Actions\Sites\CreateSite;
use App\Exceptions\SiteLimitReachedException;
use App\Http\Requests\StoreSiteRequest;
use App\Models\Template;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function store(
        StoreSiteRequest $request,
        WorkspaceContext $workspaceContext,
        CreateSite $createSite,
    ): RedirectResponse {
        $workspace = $workspaceContext->current();
        abort_if($workspace === null, 403);

        $template = Template::query()
            ->where('public_id', $request->string('template')->toString())
            ->where('is_official', true)
            ->firstOrFail();

        try {
            $site = $createSite->create(
                $workspace,
                $template,
                $request->string('name')->toString(),
            );
        } catch (SiteLimitReachedException) {
            throw ValidationException::withMessages([
                'site' => 'Достигнут лимит активных сайтов для текущего рабочего пространства.',
            ]);
        }

        return to_route('dashboard', ['site' => $site->public_id]);
    }
}
