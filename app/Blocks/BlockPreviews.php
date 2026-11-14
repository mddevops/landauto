<?php

namespace App\Blocks;

use App\Models\BlockVersion;
use App\Models\BlockVersionPreview;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Block Version preview screenshots (D-130): the Block owner uploads or replaces one per version,
 * on publish or later. Files live on a private disk and are served through an authenticated route.
 */
final class BlockPreviews
{
    public function __construct(private BlockAuthoringAuthorization $authorization) {}

    /**
     * @param  UploadedFile  $file  already validated with {@see ImageUpload}
     */
    public function attach(User $actor, BlockVersion $version, UploadedFile $file): BlockVersionPreview
    {
        $block = $version->definition;

        if (! $this->authorization->canEdit($actor, $block)) {
            throw new AuthorizationException;
        }

        $mime = (string) $file->getMimeType();
        $info = getimagesize((string) $file->getRealPath());
        abort_if($info === false || ! isset(ImageUpload::TYPES[$mime]), 422);

        $preview = new BlockVersionPreview;
        $preview->public_id = $preview->newUniqueId();
        $preview->path = "block-previews/{$block->public_id}/{$preview->public_id}.".ImageUpload::TYPES[$mime];
        $preview->mime_type = $mime;
        $preview->size_bytes = (int) $file->getSize();
        $preview->width = (int) $info[0];
        $preview->height = (int) $info[1];
        $preview->version()->associate($version);
        $preview->created_by_user_id = $actor->id;

        $disk = Storage::disk(BlockVersionPreview::DISK);
        $disk->putFileAs(dirname($preview->path), $file, basename($preview->path));

        try {
            $replaced = DB::transaction(function () use ($preview, $version): ?BlockVersionPreview {
                $current = BlockVersionPreview::query()->where('block_version_id', $version->id)->lockForUpdate()->first();
                $current?->delete();
                $preview->save();

                return $current;
            });
        } catch (Throwable $exception) {
            $disk->delete($preview->path);

            throw $exception;
        }

        if ($replaced !== null) {
            $disk->delete($replaced->path);
        }

        $this->log('block_preview_saved', $actor, $version);

        return $preview;
    }

    public function remove(User $actor, BlockVersion $version): void
    {
        if (! $this->authorization->canEdit($actor, $version->definition)) {
            throw new AuthorizationException;
        }

        $preview = $version->preview;

        if ($preview === null) {
            return;
        }

        $preview->delete();
        Storage::disk(BlockVersionPreview::DISK)->delete($preview->path);
        $this->log('block_preview_removed', $actor, $version);
    }

    public static function url(?BlockVersionPreview $preview): ?string
    {
        return $preview === null ? null : route('block-previews.show', $preview->public_id, false);
    }

    /**
     * Preview of the newest version that has one, per Block Definition id.
     *
     * @param  list<int>  $definitionIds
     * @return array<int, string>
     */
    public static function latestUrls(array $definitionIds): array
    {
        if ($definitionIds === []) {
            return [];
        }

        $urls = [];

        BlockVersionPreview::query()
            ->join('block_versions', 'block_versions.id', '=', 'block_version_previews.block_version_id')
            ->whereIn('block_versions.block_definition_id', $definitionIds)
            ->orderByDesc('block_versions.id')
            ->get(['block_version_previews.public_id', 'block_versions.block_definition_id'])
            ->each(function (BlockVersionPreview $preview) use (&$urls): void {
                $definitionId = (int) $preview->getAttribute('block_definition_id');
                $urls[$definitionId] ??= (string) self::url($preview);
            });

        return $urls;
    }

    private function log(string $action, User $actor, BlockVersion $version): void
    {
        $block = $version->definition;
        $prefix = $block->isPlatformOwned() ? 'platform' : 'developer';

        Log::info("{$prefix}.{$action}", ['block' => $block->public_id, 'version' => $version->version, 'actor_user_id' => $actor->id]);
    }
}
