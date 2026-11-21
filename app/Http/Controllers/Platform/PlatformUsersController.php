<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\PlatformUserDirectory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Пользователи»: every registered account, for Super Admin only.
 */
class PlatformUsersController extends Controller
{
    public function index(PlatformUserDirectory $directory): Response
    {
        $users = $directory->all();

        return Inertia::render('platform/users/index', [
            'users' => $users,
            'total' => count($users),
        ]);
    }
}
