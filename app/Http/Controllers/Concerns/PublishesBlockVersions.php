<?php

namespace App\Http\Controllers\Concerns;

use App\Blocks\BlockPublisher;
use App\Exceptions\BlockPublishException;
use App\Http\Requests\Blocks\PublishBlockRequest;
use App\Models\BlockDefinition;
use App\Models\User;
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

        Inertia::flash('toast', ['type' => 'success', 'message' => "Опубликована версия {$version->version}."]);
    }
}
