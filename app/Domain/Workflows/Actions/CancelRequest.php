<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\WorkflowEngine;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** The requester withdraws a request that is still open. */
class CancelRequest
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(User $actor, WorkflowRequest $request): WorkflowRequest
    {
        if (Gate::forUser($actor)->denies('cancel', $request)) {
            throw new AuthorizationException(__('Tego wniosku nie można wycofać.'));
        }

        DB::transaction(function () use ($actor, $request) {
            $request->forceFill(['status' => WorkflowStatus::Cancelled, 'completed_at' => now(), 'stage_due_at' => null])->save();
            WorkflowAction::query()->create(['request_id' => $request->id, 'user_id' => $actor->id, 'stage_position' => $request->current_position, 'action' => 'cancelled']);
            $this->engine->closeStageTasks($request, TaskStatus::Cancelled, $actor);
            $this->engine->audit($request, 'workflow.cancelled', [], $actor);
        });

        return $request->refresh();
    }
}
