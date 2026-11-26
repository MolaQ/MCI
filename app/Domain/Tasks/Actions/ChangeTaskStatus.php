<?php

namespace App\Domain\Tasks\Actions;

use App\Enums\TaskStatus;
use App\Jobs\NotifyTask;
use App\Models\Task;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * One-click status change by the author, an assignee or a manager. For a
 * group or role task one completion completes it for everybody. The
 * author hears when someone else finishes or cancels their task.
 */
class ChangeTaskStatus
{
    public function handle(User $actor, Task $task, TaskStatus $status): Task
    {
        if (Gate::forUser($actor)->denies('work', $task)) {
            throw new AuthorizationException(__('Nie możesz zmienić statusu tego zadania.'));
        }
        if ($status === TaskStatus::Cancelled && Gate::forUser($actor)->denies('update', $task)) {
            throw new AuthorizationException(__('Zadanie może anulować tylko jego autor.'));
        }

        $from = $task->status;
        if ($from === $status) {
            return $task;
        }

        $task->forceFill([
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
            'completed_by' => $status === TaskStatus::Done ? $actor->id : null,
        ])->save();

        Audit::log('task.status', $task, ['title' => $task->title, 'from' => $from->value, 'to' => $status->value], $actor, $task->school_id);

        if (in_array($status, [TaskStatus::Done, TaskStatus::Cancelled], true) && $task->created_by && $task->created_by !== $actor->id) {
            NotifyTask::dispatch($task->id, [$task->created_by], $status === TaskStatus::Done ? 'completed' : 'cancelled', $actor->id)->afterCommit();
        }

        return $task;
    }
}
