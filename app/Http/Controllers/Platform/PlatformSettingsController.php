<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingsRequest;
use App\Platform\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function edit(PlatformSettings $settings): Response
    {
        return Inertia::render('platform/settings/edit', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request, PlatformSettings $settings): RedirectResponse
    {
        $settings->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Настройки платформы сохранены.']);

        return to_route('platform.settings.edit');
    }
}
