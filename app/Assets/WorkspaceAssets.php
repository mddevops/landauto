<?php

namespace App\Assets;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared Workspace media library (D-087 for Phase 8). Storage paths are always generated on the
 * server from public IDs; using an asset on a Site copies the file into a new, independent
 * SiteAsset, so deleting a Workspace Asset never touches Sites or Published Versions.
 */
final class WorkspaceAssets
{
    /**
     * Stores an upload that already passed the shared ImageUpload validation.
     */
    public function upload(Workspace $workspace, UploadedFile $file): WorkspaceAsset
    {
        $mime = (string) $file->getMimeType();
        $info = getimagesize((string) $file->getRealPath());

        if ($info === false || ! isset(ImageUpload::TYPES[$mime])) {
            throw ValidationException::withMessages(['file' => 'Файл повреждён или не является изображением.']);
        }

        $asset = new WorkspaceAsset(['original_name' => Str::limit(trim($file->getClientOriginalName()) ?: 'image', 250, '')]);
        $asset->public_id = $asset->newUniqueId();
        $asset->path = "workspace-assets/{$workspace->public_id}/{$asset->public_id}.".ImageUpload::TYPES[$mime];
        $asset->mime_type = $mime;
        $asset->size_bytes = (int) $file->getSize();
        $asset->width = (int) $info[0];
        $asset->height = (int) $info[1];
        $asset->workspace()->associate($workspace);

        $disk = Storage::disk(WorkspaceAsset::DISK);
        $disk->putFileAs(dirname($asset->path), $file, basename($asset->path));

        try {
            DB::transaction(fn () => $asset->save());
        } catch (Throwable $exception) {
            $disk->delete($asset->path);

            throw $exception;
        }

        return $asset;
    }

    /**
     * Copies the file into a new SiteAsset of a Site in the same Workspace.
     */
    public function copyToSite(WorkspaceAsset $source, Site $site): SiteAsset
    {
        if ($source->workspace_id !== $site->workspace_id) {
            throw ValidationException::withMessages(['site' => 'Выберите сайт этого пространства.']);
        }

        $disk = Storage::disk(SiteAsset::DISK);

        if (! $disk->exists($source->path)) {
            throw ValidationException::withMessages(['site' => 'Файл изображения недоступен. Загрузите его заново.']);
        }

        $asset = new SiteAsset(['original_name' => $source->original_name]);
        $asset->public_id = $asset->newUniqueId();
        $asset->path = "site-assets/{$site->public_id}/{$asset->public_id}.".ImageUpload::TYPES[$source->mime_type];
        $asset->mime_type = $source->mime_type;
        $asset->size_bytes = $source->size_bytes;
        $asset->width = $source->width;
        $asset->height = $source->height;
        $asset->site()->associate($site);

        $disk->copy($source->path, $asset->path);

        try {
            DB::transaction(fn () => $asset->save());
        } catch (Throwable $exception) {
            $disk->delete($asset->path);

            throw $exception;
        }

        Log::info('workspace.asset_copied_to_site', [
            'workspace_asset' => $source->public_id,
            'site' => $site->public_id,
            'site_asset' => $asset->public_id,
        ]);

        return $asset;
    }

    public function delete(WorkspaceAsset $asset): void
    {
        DB::transaction(fn () => $asset->delete());
        Storage::disk(WorkspaceAsset::DISK)->delete($asset->path);
    }
}
