<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowAction;
use Illuminate\Support\Facades\Gate;

/** Images on a decision are visible to whoever may see the request. */
class WorkflowActionPolicy
{
    public function view(User $actor, WorkflowAction $action): bool
    {
        return Gate::forUser($actor)->allows('view', $action->request);
    }
}
