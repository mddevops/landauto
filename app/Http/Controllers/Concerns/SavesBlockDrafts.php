<?php

namespace App\Http\Controllers\Concerns;

use App\Blocks\BlockStudio;
use App\Exceptions\BlockDraftConflictException;
use App\Http\Requests\Blocks\SaveBlockDraftRequest;
use App\Models\BlockDefinition;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait SavesBlockDrafts
{
    private function saveDraft(BlockStudio $studio, SaveBlockDraftRequest $request, User $actor, BlockDefinition $block): void
    {
        try {
            $studio->save($actor, $block, $request->sources(), $request->preview(), $request->integer('revision'));
        } catch (BlockDraftConflictException) {
            throw ValidationException::withMessages([
                'draft' => 'Черновик уже изменён в другой вкладке или другим автором. Обновите страницу, чтобы не потерять изменения.',
            ]);
        }
    }
}
