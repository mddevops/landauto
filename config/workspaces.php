<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace team (Phase 8)
    |--------------------------------------------------------------------------
    |
    | Invitation links are single-use and expire; only a SHA-256 hash of the
    | token is stored. Seat limits come from the `max_members` entitlement.
    |
    */

    'invitation_ttl_hours' => max(1, (int) env('WORKSPACE_INVITATION_TTL_HOURS', 168)),

];
