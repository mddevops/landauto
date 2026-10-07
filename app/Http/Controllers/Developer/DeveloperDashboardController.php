<?php

namespace App\Http\Controllers\Developer;

use App\Developers\DeveloperAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveDeveloperProfile;
use App\Models\DeveloperProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Панель разработчика»: read-only view of the current User's own active Developer Profile and its
 * effective creator capabilities (D-118). No Workspace context is involved.
 */
class DeveloperDashboardController extends Controller
{
    public function __invoke(Request $request, DeveloperAuthorization $authorization): Response
    {
        /** @var DeveloperProfile $profile */
        $profile = $request->attributes->get(EnsureActiveDeveloperProfile::ATTRIBUTE);

        return Inertia::render('developer/dashboard', [
            'profile' => [
                'public_id' => $profile->public_id,
                'display_name' => $profile->display_name,
                'slug' => $profile->slug,
                'status_label' => $profile->status->label(),
                'bio' => $profile->bio,
            ],
            'capabilities' => $authorization->capabilities($profile),
        ]);
    }
}
