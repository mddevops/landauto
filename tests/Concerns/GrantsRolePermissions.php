<?php

namespace Tests\Concerns;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Support\WorkspacePermissionResolver;

/**
 * Replaces a system role's permission list for one test, to exercise authorization combinations
 * the shipped matrix does not contain (for example a selected-Sites member with import rights).
 */
trait GrantsRolePermissions
{
    /** @var array<string, list<WorkspacePermission>> */
    private array $grantedRolePermissions = [];

    /**
     * @param  list<WorkspacePermission>  $permissions
     */
    protected function grant(WorkspaceRole $role, array $permissions): void
    {
        $this->grantedRolePermissions[$role->value] = $permissions;

        $this->app->instance(WorkspacePermissionResolver::class, new class($this->grantedRolePermissions) extends WorkspacePermissionResolver
        {
            /**
             * @param  array<string, list<WorkspacePermission>>  $grants
             */
            public function __construct(private array $grants) {}

            public function forRole(WorkspaceRole $role): array
            {
                return $this->grants[$role->value] ?? parent::forRole($role);
            }
        });
        $this->app->forgetScopedInstances();
    }
}
