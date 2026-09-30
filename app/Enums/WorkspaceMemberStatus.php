<?php

namespace App\Enums;

/**
 * Only an active membership grants access to its Workspace (TENANCY.md §4).
 * Removing a member deletes the membership row; content stays with the Workspace.
 */
enum WorkspaceMemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
}
