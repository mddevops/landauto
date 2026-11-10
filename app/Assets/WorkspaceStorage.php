<?php

namespace App\Assets;

use App\Models\SiteAsset;
use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use App\Support\WorkspaceEntitlements;
use Illuminate\Validation\ValidationException;

/**
 * Cumulative Workspace file quota across Site Assets and Workspace Assets (X-017 / D-106).
 */
final class WorkspaceStorage
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    public function usedBytes(Workspace $workspace): int
    {
        $siteBytes = (int) SiteAsset::query()
            ->whereIn('site_id', $workspace->sites()->select('id'))
            ->sum('size_bytes');

        $workspaceBytes = (int) WorkspaceAsset::query()
            ->where('workspace_id', $workspace->id)
            ->sum('size_bytes');

        return $siteBytes + $workspaceBytes;
    }

    public function assertCanStore(Workspace $workspace, int $additionalBytes): void
    {
        $limitMb = $this->entitlements->storageLimitMb($workspace);

        if ($limitMb === null) {
            return;
        }

        $limitBytes = $limitMb * 1024 * 1024;

        if ($this->usedBytes($workspace) + max(0, $additionalBytes) <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => "Недостаточно места в хранилище пространства (лимит {$limitMb} МБ). Удалите неиспользуемые файлы или обратитесь в поддержку.",
        ]);
    }
}
