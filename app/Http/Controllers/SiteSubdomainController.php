<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSiteSubdomainRequest;
use App\Models\Site;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Explicit change of a Site's Landflow subdomain (`manage_domains`). The public host switches
 * immediately; the previous address stops resolving.
 */
class SiteSubdomainController extends Controller
{
    public function update(UpdateSiteSubdomainRequest $request, Site $site): RedirectResponse
    {
        try {
            $site->forceFill(['subdomain' => $request->validated('subdomain')])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['subdomain' => 'Этот адрес уже занят. Выберите другой.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Адрес сайта сохранён.']);

        return to_route('sites.publishing.show', $site);
    }
}
