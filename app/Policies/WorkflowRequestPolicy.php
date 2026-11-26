<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\CurrentSchool;

/**
 * A request is visible to its author, the people who decide now or decided
 * before, people @mentioned in its history and, for oversight, those who
 * manage workflows in its school. Only current approvers decide, and never
 * on their own request.
 */
class WorkflowRequestPolicy
{
    public function view(User $actor, WorkflowRequest $request): bool
    {
        return $request->requester_id === $actor->id
            || WorkflowRequest::query()->whereKey($request->id)->visibleTo($actor)->exists()
            || $request->actions()->whereHas('mentions', fn ($m) => $m->where('mentionable_type', 'user')->where('mentionable_id', $actor->id))->exists()
            || (app(CurrentSchool::class)->id() === $request->school_id && $actor->can(Permission::WorkflowsManage->value));
    }

    public function decide(User $actor, WorkflowRequest $request): bool
    {
        return $request->status === WorkflowStatus::InProgress
            && WorkflowRequest::query()->whereKey($request->id)->awaitingDecisionBy($actor)->exists();
    }

    public function resubmit(User $actor, WorkflowRequest $request): bool
    {
        return $request->requester_id === $actor->id && $request->status === WorkflowStatus::Returned;
    }

    public function cancel(User $actor, WorkflowRequest $request): bool
    {
        return $request->requester_id === $actor->id && $request->status->isOpen();
    }
}
