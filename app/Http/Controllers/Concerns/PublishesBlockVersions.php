<?php

namespace App\Http\Controllers\Concerns;

use App\Blocks\BlockPreviews;
use App\Blocks\BlockPublisher;
use App\Exceptions\BlockPublishException;
use App\Http\Requests\Blocks\PublishBlockRequest;
use App\Http\Requests\Blocks\UploadBlockPreviewRequest;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

trait PublishesBlockVersions
{
    private function publishVersion(BlockPublisher $publisher, PublishBlockRequest $request, User $actor, BlockDefinition $block): void
    {
        try {
            $version = $publisher->publish($actor, $block, $request->integer('revision'));
        } catch (BlockPublishException $exception) {
            throw ValidationException::withMessages(['publish' => $exception->getMessage()]);
        }

        $preview = $request->file('preview');

        if ($preview instanceof UploadedFile) {
            app(BlockPreviews::class)->attach($actor, $version, $preview);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Опубликована версия {$version->version}."]);
    }

    private function saveVersionPreview(UploadBlockPreviewRequest $request, User $actor, BlockDefinition $block, string $version): void
    {
        $file = $request->file('preview');
        abort_unless($file instanceof UploadedFile, 422);

        app(BlockPreviews::class)->attach($actor, $this->blockVersion($block, $version), $file);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Превью версии {$version} сохранено."]);
    }

    private function removeVersionPreview(User $actor, BlockDefinition $block, string $version): void
    {
        app(BlockPreviews::class)->remove($actor, $this->blockVersion($block, $version));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Превью версии {$version} удалено."]);
    }

    private function blockVersion(BlockDefinition $block, string $version): BlockVersion
    {
        return $block->versions()->where('version', $version)->firstOrFail()->setRelation('definition', $block);
    }
}
