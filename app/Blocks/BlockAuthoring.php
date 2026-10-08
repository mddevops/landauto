<?php

namespace App\Blocks;

use App\Enums\BlockCategory;
use App\Enums\BlockOwnerScope;
use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Models\BlockDefinition;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Block Definition authoring (D-117, D-118): ownership is always derived on the server and never
 * changes. Creating a definition creates no Block Version; sources live in the Draft (BlockStudio).
 */
final class BlockAuthoring
{
    public function __construct(private BlockAuthoringAuthorization $authorization) {}

    public function createPlatform(User $actor, string $name, string $slug, BlockCategory $category): BlockDefinition
    {
        $this->authorize($this->authorization->canAuthorPlatformBlocks($actor));

        $block = $this->newDefinition($actor, $name, $slug, $category, BlockOwnerScope::Platform);
        $block->save();
        $this->log('block_created', $actor, $block);

        return $block;
    }

    /**
     * Owned by the actor's own active Developer Profile; browser input never selects it.
     */
    public function createDeveloper(User $actor, string $name, string $slug, BlockCategory $category): BlockDefinition
    {
        $profile = $this->authorization->developerAuthor($actor);
        $this->authorize($profile !== null);

        $block = $this->newDefinition($actor, $name, $slug, $category, BlockOwnerScope::Developer);
        $block->developerProfile()->associate($profile);
        $block->save();
        $this->log('block_created', $actor, $block);

        return $block;
    }

    public function updateMetadata(User $actor, BlockDefinition $block, string $name, BlockCategory $category): void
    {
        $this->authorize($this->authorization->canEdit($actor, $block));

        $block->name = $name;
        $block->category = $category;

        if (! $block->isDirty()) {
            return;
        }

        $block->lastEditor()->associate($actor);
        $block->save();
        $this->log('block_updated', $actor, $block);
    }

    /**
     * Customer catalog access (D-121); applies to already published versions as well.
     */
    public function updateAccess(User $actor, BlockDefinition $block, CatalogAccessMode $mode, ?Entitlement $entitlement, ?int $sitePriceMinor, ?int $workspacePriceMinor): void
    {
        $this->authorize($this->authorization->canEdit($actor, $block));

        $block->access_mode = $mode;
        $block->access_entitlement = $mode === CatalogAccessMode::Entitlement ? $entitlement : null;
        $block->site_price_minor = $mode === CatalogAccessMode::Paid ? $sitePriceMinor : null;
        $block->workspace_price_minor = $mode === CatalogAccessMode::Paid ? $workspacePriceMinor : null;
        $block->price_currency = $mode === CatalogAccessMode::Paid ? Money::DEFAULT_CURRENCY : null;

        if (! $block->isDirty()) {
            return;
        }

        $block->lastEditor()->associate($actor);
        $block->save();
        $this->log('block_access_updated', $actor, $block, ['access_mode' => $mode->value]);
    }

    private function newDefinition(User $actor, string $name, string $slug, BlockCategory $category, BlockOwnerScope $scope): BlockDefinition
    {
        $block = new BlockDefinition(['name' => $name, 'slug' => $slug, 'category' => $category]);
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

    /**
     * @param  array<string, string>  $extra
     */
    private function log(string $action, User $actor, BlockDefinition $block, array $extra = []): void
    {
        $context = ['block' => $block->public_id, 'owner_scope' => $block->owner_scope->value, ...$extra];

        if ($block->isDeveloperOwned()) {
            $context['developer_profile'] = $block->developerProfile?->public_id;
        }

        $prefix = $block->isPlatformOwned() ? 'platform' : 'developer';

        Log::info("{$prefix}.{$action}", [...$context, 'actor_user_id' => $actor->id]);
    }
}
