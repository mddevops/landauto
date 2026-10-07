<?php

namespace App\Blocks;

use App\Enums\BlockOwnerScope;
use App\Models\BlockDefinition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Block Definition authoring (D-117, D-118): ownership is always derived on the server and never
 * changes. Creating a definition creates no Block Version; schemas, preview and publishing belong
 * to later tasks.
 */
final class BlockAuthoring
{
    public function __construct(private BlockAuthoringAuthorization $authorization) {}

    public function createPlatform(User $actor, string $name, string $slug): BlockDefinition
    {
        $this->authorize($this->authorization->canAuthorPlatformBlocks($actor));

        $block = $this->newDefinition($actor, $name, $slug, BlockOwnerScope::Platform);
        $block->save();
        $this->log('block_created', $actor, $block);

        return $block;
    }

    /**
     * Owned by the actor's own active Developer Profile; browser input never selects it.
     */
    public function createDeveloper(User $actor, string $name, string $slug): BlockDefinition
    {
        $profile = $this->authorization->developerAuthor($actor);
        $this->authorize($profile !== null);

        $block = $this->newDefinition($actor, $name, $slug, BlockOwnerScope::Developer);
        $block->developerProfile()->associate($profile);
        $block->save();
        $this->log('block_created', $actor, $block);

        return $block;
    }

    public function updateMetadata(User $actor, BlockDefinition $block, string $name): void
    {
        $this->authorize($this->authorization->canEdit($actor, $block));

        $block->name = $name;

        if (! $block->isDirty()) {
            return;
        }

        $block->lastEditor()->associate($actor);
        $block->save();
        $this->log('block_updated', $actor, $block);
    }

    private function newDefinition(User $actor, string $name, string $slug, BlockOwnerScope $scope): BlockDefinition
    {
        $block = new BlockDefinition(['name' => $name, 'slug' => $slug]);
        $block->owner_scope = $scope;
        $block->creator()->associate($actor);
        $block->lastEditor()->associate($actor);

        return $block;
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    private function log(string $action, User $actor, BlockDefinition $block): void
    {
        $context = ['block' => $block->public_id, 'owner_scope' => $block->owner_scope->value];

        if ($block->isDeveloperOwned()) {
            $context['developer_profile'] = $block->developerProfile?->public_id;
        }

        $prefix = $block->isPlatformOwned() ? 'platform' : 'developer';

        Log::info("{$prefix}.{$action}", [...$context, 'actor_user_id' => $actor->id]);
    }
}
