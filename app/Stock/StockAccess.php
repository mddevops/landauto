<?php

namespace App\Stock;

use App\Enums\Entitlement;
use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stock is Workspace-scoped and requires multi_page_sites plus manage_stock (ADR-012).
 */
final class StockAccess
{
    public function __construct(
        private WorkspaceContext $context,
        private WorkspaceAuthorization $authorization,
        private WorkspaceEntitlements $entitlements,
    ) {}

    public function allows(User $user, ?Workspace $workspace = null): bool
    {
        $workspace ??= $this->context->current();

        return $workspace !== null
            && $this->entitlements->allows($workspace, Entitlement::MultiPageSites)
            && $this->authorization->allowsForWorkspace($user, $workspace, WorkspacePermission::ManageStock);
    }

    public function authorize(User $user): Workspace
    {
        $workspace = $this->context->current();

        if ($workspace === null || ! $this->allows($user, $workspace)) {
            throw new NotFoundHttpException;
        }

        return $workspace;
    }
}
