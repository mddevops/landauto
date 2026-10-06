<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use Illuminate\Support\Facades\Gate;

class UpdateWorkspaceRequest extends StoreWorkspaceRequest
{
    public function authorize(): bool
    {
        return Gate::allows(WorkspacePermission::EditWorkspace->value);
    }
}
