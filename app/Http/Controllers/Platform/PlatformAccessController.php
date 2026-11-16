<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AssignWorkspacePlanRequest;
use App\Http\Requests\Platform\SyncPlatformRolesRequest;
use App\Models\Plan;
use App\Models\User;
use App\Platform\PlatformAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Доступы»: Super Admin beta panel to assign Workspace plans and platform roles before paid billing (D-133).
 */
class PlatformAccessController extends Controller
{
    public function __construct(private PlatformAccess $access) {}

    public function index(Request $request): Response
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));
        $user = $email !== '' ? $this->access->findByEmail($email) : null;

        return Inertia::render('platform/access/index', [
            'query' => $email,
            'user' => $user === null ? null : $this->access->present($user),
            'notFound' => $email !== '' && $user === null,
            'roleOptions' => array_map(fn (PlatformRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], PlatformRole::cases()),
            'planOptions' => [
                ['value' => Plan::FREE_KEY, 'label' => 'Базовый'],
                ['value' => Plan::FULL_KEY, 'label' => 'Полный'],
            ],
        ]);
    }

    public function syncRoles(SyncPlatformRolesRequest $request): RedirectResponse
    {
        $user = $request->target();
        $this->access->syncRoles($this->actor($request), $user, $request->roles());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Роли платформы для {$user->email} сохранены."]);

        return to_route('platform.access.index', ['email' => $user->email]);
    }

    public function assignPlan(AssignWorkspacePlanRequest $request): RedirectResponse
    {
        $owner = $request->owner();
        $plan = $this->access->assignPlan(
            $this->actor($request),
            $owner,
            $request->workspace(),
            $request->planKey(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Тариф «{$plan->name}» назначен пространству «{$request->workspace()->name}».",
        ]);

        return to_route('platform.access.index', ['email' => $owner->email]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
