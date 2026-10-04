<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSiteDesignRequest;
use App\Models\Site;
use App\Support\SiteDesignTokens;
use Illuminate\Http\RedirectResponse;

class SiteDesignController extends Controller
{
    public function __invoke(UpdateSiteDesignRequest $request, Site $site): RedirectResponse
    {
        $site->update(['design_tokens' => $request->safe()->only(array_keys(SiteDesignTokens::DEFAULTS))]);

        return back();
    }
}
