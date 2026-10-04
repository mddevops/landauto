<?php

namespace App\Http\Controllers\Forms;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\WorkspacePermission;
use App\Forms\Blacklist;
use App\Http\Controllers\Controller;
use App\Models\BlacklistEntry;
use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\WorkspaceAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Tenant blacklist entries managed from «Защита форм». Site entries need `edit_forms` (X-021);
 * Workspace entries need `edit_workspace` for the Site's own Workspace. The owner is always
 * derived from the Site; Global entries are never reachable here.
 */
class SiteBlacklistController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private WorkspaceAuthorization $authorization,
    ) {}

    public function store(Request $request, Site $site, Blacklist $blacklist): RedirectResponse
    {
        $this->scope->site($site);

        $validated = $request->validate([
            'scope' => ['required', Rule::in([BlacklistScope::Site->value, BlacklistScope::Workspace->value])],
            'type' => ['required', Rule::enum(BlacklistType::class)],
            'value' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ], [], [
            'scope' => 'Где действует',
            'type' => 'Тип',
            'value' => 'Значение',
            'reason' => 'Причина',
            'expires_in_days' => 'Срок, дней',
        ]);

        $scope = BlacklistScope::from($validated['scope']);
        $this->authorizeScope($site, $scope);
        $type = BlacklistType::from($validated['type']);
        $value = $blacklist->normalize($type, $validated['value']);

        if ($value === null) {
            throw ValidationException::withMessages(['value' => $type === BlacklistType::Ip ? 'Укажите корректный IP-адрес.' : 'Укажите телефон полностью, например +7 999 111-22-33.']);
        }

        $entry = new BlacklistEntry([
            'reason' => $validated['reason'] ?? null,
            'expires_at' => isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null,
        ]);
        $entry->scope = $scope;
        $entry->workspace_id = $scope === BlacklistScope::Workspace ? $site->workspace_id : null;
        $entry->site_id = $scope === BlacklistScope::Site ? $site->id : null;
        $entry->type = $type;
        $entry->value = $value;
        $entry->created_by_user_id = $request->user()?->id;
        $entry->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Запись добавлена в чёрный список.']);

        return back();
    }

    public function destroy(Site $site, string $entry): RedirectResponse
    {
        $this->scope->site($site);

        $model = BlacklistEntry::query()
            ->where('public_id', $entry)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('scope', BlacklistScope::Site->value)->where('site_id', $site->id))
                ->orWhere(fn ($query) => $query->where('scope', BlacklistScope::Workspace->value)->where('workspace_id', $site->workspace_id)))
            ->firstOrFail();

        $this->authorizeScope($site, $model->scope);
        $model->delete();

        return back();
    }

    private function authorizeScope(Site $site, BlacklistScope $scope): void
    {
        $allowed = $scope === BlacklistScope::Workspace
            ? ($user = request()->user()) !== null && $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditWorkspace)
            : Gate::allows('editForms', $site);

        abort_unless($allowed, 403);
    }
}
