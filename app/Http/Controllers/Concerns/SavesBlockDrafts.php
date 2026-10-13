<?php

namespace App\Http\Controllers\Concerns;

use App\Blocks\BlockStudio;
use App\Blocks\LegacyBlockMigration;
use App\Exceptions\BlockDraftConflictException;
use App\Http\Requests\Blocks\SaveBlockDraftRequest;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

trait SavesBlockDrafts
{
    private const DRAFT_CONFLICT = 'Черновик уже изменён в другой вкладке или другим автором. Обновите страницу, чтобы не потерять изменения.';

    private function saveDraft(BlockStudio $studio, SaveBlockDraftRequest $request, User $actor, BlockDefinition $block): void
    {
        try {
            $studio->save($actor, $block, $request->sources(), $request->preview(), $request->integer('revision'));
        } catch (BlockDraftConflictException) {
            throw ValidationException::withMessages([
                'draft' => self::DRAFT_CONFLICT,
            ]);
        }
    }

    private function copyLegacyVersionToDraft(BlockStudio $studio, Request $request, User $actor, BlockDefinition $block, string $version): void
    {
        $validated = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $legacy = $block->versions()->where('version', $version)->first();

        if (! $legacy instanceof BlockVersion || ! LegacyBlockMigration::isLegacy($legacy)) {
            throw ValidationException::withMessages(['draft' => 'В черновик можно скопировать только устаревшую изолированную версию этого блока.']);
        }

        try {
            $studio->copyLegacyVersion($actor, $block, $legacy, (int) $validated['revision']);
        } catch (BlockDraftConflictException) {
            throw ValidationException::withMessages(['draft' => self::DRAFT_CONFLICT]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Код версии {$legacy->version} скопирован в черновик. Проверьте предпросмотр и одобрите нативную версию."]);
    }
}
