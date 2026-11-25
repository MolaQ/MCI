<?php

namespace App\Domain\Tasks\Actions;

use App\Models\Task;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Soft-deletes a task (kept for the log); images stay until a purge. */
class DeleteTask
{
    public function handle(User $actor, Task $task): void
    {
        if (Gate::forUser($actor)->denies('delete', $task)) {
            throw new AuthorizationException(__('Nie możesz usunąć tego zadania.'));
        }

        $task->delete();
        Audit::log('task.deleted', $task, ['title' => $task->title, 'personal' => $task->is_personal], $actor, $task->school_id);
    }
}
