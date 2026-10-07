<?php

namespace App\Http\Controllers\Platform;

use App\Developers\DeveloperProfiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreDeveloperProfileRequest;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Разработчики»: Super Admin management of Developer Profiles (`manage_developers`, D-093).
 * Profiles are addressed by public ID only and are never hard-deleted.
 */
class DeveloperProfileController extends Controller
{
    public function __construct(private DeveloperProfiles $profiles) {}

    public function index(): Response
    {
        return Inertia::render('platform/developers/index', [
            'developers' => DeveloperProfile::query()
                ->with('user:id,email')
                ->orderBy('display_name')
                ->orderBy('id')
                ->get()
                ->map(fn (DeveloperProfile $profile): array => [
                    'public_id' => $profile->public_id,
                    'display_name' => $profile->display_name,
                    'slug' => $profile->slug,
                    'email' => $profile->user->email,
                    'status' => $profile->status->value,
                    'status_label' => $profile->status->label(),
                    'created_at' => $profile->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreDeveloperProfileRequest $request): RedirectResponse
    {
        /** @var array{display_name: string, slug: string, bio: string|null} $data */
        $data = $request->safe()->only(['display_name', 'slug', 'bio']);

        try {
            $profile = $this->profiles->grant($this->actor($request), $request->owner(), $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Этот пользователь или slug уже заняты. Обновите страницу и проверьте данные.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Профиль разработчика «{$profile->display_name}» создан."]);

        return to_route('platform.developers.index');
    }

    public function suspend(Request $request, DeveloperProfile $developer): RedirectResponse
    {
        $this->profiles->suspend($this->actor($request), $developer);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Профиль «{$developer->display_name}» приостановлен."]);

        return to_route('platform.developers.index');
    }

    public function reactivate(Request $request, DeveloperProfile $developer): RedirectResponse
    {
        $this->profiles->reactivate($this->actor($request), $developer);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Профиль «{$developer->display_name}» восстановлен."]);

        return to_route('platform.developers.index');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
